<?php

use App\Actions\ChangeAssignment;
use App\Actions\DeleteAssignment;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Assignments\AssignmentController;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\Project;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Pages\Employees\EmployeePage;
use App\Policies\AssignmentPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, DeleteAssignment::class, ChangeAssignment::class, UpdateTrimmingAssignments::class, AssignmentPolicy::class, Assignment::class, EmployeePage::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * An Employee who may manage everyone's Assignments.
 */
function managerOfAssignments(): Employee
{
    return employeeWith([Permission::ManageAssignments->value => Scope::Everyone]);
}

/**
 * The dates the Assignment's Pay takes effect, earliest first.
 *
 * @return array<int, string>
 */
function payDatesOf(Assignment $assignment): array
{
    return $assignment->payEntries()->orderBy('effective_from')->pluck('effective_from')
        ->map(fn (mixed $date): string => substr((string) $date, 0, 10))
        ->all();
}

it('deletes an unused Assignment with its Pay, after which its Project can be deleted', function () {
    $ana = Employee::factory()->startedLongAgo()->create();
    $relaunch = Project::factory()->create(['start_date' => '2026-01-05']);
    $assignment = Assignment::factory()->for($ana)->for($relaunch)->create(['start_date' => '2026-02-01']);
    PayEntry::factory()->for($assignment)->create();

    actingAs(managerOfAssignments())
        ->delete(route('assignments.destroy', $assignment))
        ->assertRedirect(route('employees.show', ['employee' => $ana, 'tab' => 'assignments']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Assignment deleted.']);

    expect(Assignment::query()->whereKey($assignment->id)->exists())->toBeFalse()
        ->and(PayEntry::query()->count())->toBe(0);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->delete(route('projects.destroy', $relaunch))
        ->assertSessionHasNoErrors();

    expect(Project::query()->whereKey($relaunch->id)->exists())->toBeFalse();
});

it('refuses to delete an Assignment with Work Entries or Travel Requests, which can only be ended', function (string $record) {
    $assignment = Assignment::factory()
        ->for(Employee::factory()->startedLongAgo())
        ->for(Project::factory()->state(['name' => 'Relaunch', 'start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-02-01']);
    PayEntry::factory()->for($assignment)->create();
    $record === 'Work Entry'
        ? WorkEntry::factory()->for($assignment)->create()
        : TravelRequest::factory()->for($assignment)->create(['employee_id' => $assignment->employee_id]);

    actingAs(managerOfAssignments())
        ->delete(route('assignments.destroy', $assignment))
        ->assertSessionHasErrors(['assignment' => 'The Assignment to Relaunch has Work Entries or Travel Requests, so it can\'t be deleted. Set an end date instead.']);

    expect(Assignment::query()->whereKey($assignment->id)->exists())->toBeTrue()
        ->and(PayEntry::query()->count())->toBe(1);
})->with(['Work Entry', 'Travel Request']);

it('refuses to delete an Assignment for someone who may not manage that Employee\'s Assignments', function () {
    $assignment = Assignment::factory()->for(Employee::factory()->startedLongAgo())->create();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Reports, Permission::ViewAssignments->value => Scope::Everyone]))
        ->delete(route('assignments.destroy', $assignment))
        ->assertForbidden();

    expect(Assignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('offers Delete only on unused Assignments other than the Internal Assignment, and only to those who manage them', function () {
    $ana = Employee::factory()->startedLongAgo()->create();
    $internal = Assignment::factory()->internal()->for($ana)->create();
    $unused = Assignment::factory()->for($ana)->create(['start_date' => '2026-01-10']);
    $worked = Assignment::factory()->for($ana)->create(['start_date' => '2026-01-20']);
    WorkEntry::factory()->for($worked)->create();
    $travelled = Assignment::factory()->for($ana)->create(['start_date' => '2026-01-30']);
    TravelRequest::factory()->for($travelled)->create(['employee_id' => $ana->id]);

    $offersDelete = fn (Employee $viewer, array $expected) => actingAs($viewer)
        ->get(route('employees.show', ['employee' => $ana, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments', fn (Collection $rows): bool => $rows->pluck('can_delete', 'id')->all() === $expected));

    $offersDelete(managerOfAssignments(), [$internal->id => false, $unused->id => true, $worked->id => false, $travelled->id => false]);
    $offersDelete(employeeWith([Permission::ViewAssignments->value => Scope::Everyone]), [$internal->id => false, $unused->id => false, $worked->id => false, $travelled->id => false]);
});

it('removes Pay dated after an Assignment\'s new end when it is ended earlier by hand', function () {
    $assignment = Assignment::factory()
        ->for(Employee::factory()->startedLongAgo())
        ->for(Project::factory()->state(['start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-01-05', 'end_date' => null]);
    foreach (['2026-01-05', '2026-04-30', '2026-05-01', '2026-09-01'] as $day) {
        PayEntry::factory()->for($assignment)->create(['effective_from' => $day]);
    }

    actingAs(managerOfAssignments())
        ->patch(route('assignments.update', $assignment), [
            'job_title' => 'Developer',
            'start_date' => '2026-01-05',
            'end_date' => '2026-04-30',
            'capacity' => 60,
        ])
        ->assertSessionHasNoErrors();

    expect(payDatesOf($assignment))->toBe(['2026-01-05', '2026-04-30']);
});

/**
 * An Employee with a Manager, their Internal Assignment, an Assignment to a
 * Project and someone else's Assignment, each with Pay from 1 January and
 * 1 June 2026.
 *
 * @return array{Employee, Assignment, Assignment, Assignment}
 */
function assignmentsPaidFromJanuaryAndJune(): array
{
    $cara = Employee::factory()->create(['start_date' => '2024-01-01', 'manager_id' => fn () => Employee::factory()->create()->id]);
    $internal = Assignment::factory()->internal()->for($cara)->create();
    $assignment = Assignment::factory()->for($cara)->for(Project::factory()->state(['start_date' => '2024-01-01']))->create(['start_date' => '2026-01-01', 'end_date' => null]);
    $other = Assignment::factory()->for(Employee::factory()->startedLongAgo())->for(Project::factory()->state(['start_date' => '2024-01-01']))->create(['start_date' => '2026-01-01', 'end_date' => null]);
    foreach ([$internal, $assignment, $other] as $each) {
        PayEntry::factory()->for($each)->createMany([['effective_from' => '2026-01-01'], ['effective_from' => '2026-06-01']]);
    }

    return [$cara, $internal, $assignment, $other];
}

it('removes Pay dated after a Leaving Date from every Assignment it trims, the Internal Assignment too', function () {
    [$cara, $internal, $assignment, $other] = assignmentsPaidFromJanuaryAndJune();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-05-31']))
        ->assertSessionHasNoErrors();

    expect(payDatesOf($internal))->toBe(['2026-01-01'])
        ->and(payDatesOf($assignment))->toBe(['2026-01-01'])
        ->and(payDatesOf($other))->toBe(['2026-01-01', '2026-06-01']);
});

it('removes Pay dated after a Project\'s end from the Assignments it trims', function () {
    [, $internal, $assignment, $other] = assignmentsPaidFromJanuaryAndJune();
    $project = $assignment->project;

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), ['name' => $project->name, 'client_id' => $project->client_id, 'start_date' => '2024-01-01', 'end_date' => '2026-05-31'])
        ->assertSessionHasNoErrors();

    expect(payDatesOf($assignment))->toBe(['2026-01-01'])
        ->and(payDatesOf($internal))->toBe(['2026-01-01', '2026-06-01'])
        ->and(payDatesOf($other))->toBe(['2026-01-01', '2026-06-01']);
});
