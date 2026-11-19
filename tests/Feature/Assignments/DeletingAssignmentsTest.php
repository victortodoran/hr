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

    $canDelete = fn (Employee $viewer): array => collect(actingAs($viewer)
        ->get(route('employees.show', ['employee' => $ana, 'tab' => 'assignments']))
        ->inertiaProps('assignments'))
        ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['can_delete']])
        ->all();

    expect($canDelete(managerOfAssignments()))->toBe([$internal->id => false, $unused->id => true, $worked->id => false, $travelled->id => false])
        ->and($canDelete(employeeWith([Permission::ViewAssignments->value => Scope::Everyone])))->toBe([$internal->id => false, $unused->id => false, $worked->id => false, $travelled->id => false]);
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

it('removes Pay dated after the day a Leaving Date or a Project end trims Assignments to', function (string $trimmedBy) {
    $cara = Employee::factory()->create(['start_date' => '2024-01-01', 'manager_id' => fn () => Employee::factory()->create()->id]);
    $internal = Assignment::factory()->internal()->for($cara)->create();
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    $assignment = Assignment::factory()->for($cara)->for($project)->create(['start_date' => '2026-01-01', 'end_date' => null]);
    $other = Assignment::factory()->for(Employee::factory()->startedLongAgo())->for(Project::factory()->state(['start_date' => '2024-01-01']))->create(['start_date' => '2026-01-01', 'end_date' => null]);
    foreach ([$internal, $assignment, $other] as $each) {
        PayEntry::factory()->for($each)->createMany([['effective_from' => '2026-01-01'], ['effective_from' => '2026-06-01']]);
    }

    if ($trimmedBy === 'Leaving Date') {
        actingAs(employeeWhoManagesEmployees())
            ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-05-31']))
            ->assertSessionHasNoErrors();
        expect(payDatesOf($internal))->toBe(['2026-01-01']);
    } else {
        actingAs(employeeWith([Permission::ManageProjects->value => null]))
            ->patch(route('projects.update', $project), ['name' => $project->name, 'client_id' => $project->client_id, 'start_date' => '2024-01-01', 'end_date' => '2026-05-31'])
            ->assertSessionHasNoErrors();
        expect(payDatesOf($internal))->toBe(['2026-01-01', '2026-06-01']);
    }

    expect(payDatesOf($assignment))->toBe(['2026-01-01'])
        ->and(payDatesOf($other))->toBe(['2026-01-01', '2026-06-01']);
})->with(['Leaving Date', 'Project end']);
