<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Support\Bookings;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, ProjectController::class, ClientController::class, Assignment::class, Employee::class, Bookings::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * Cara, working since 2024 with her Internal Assignment (no planned share)
 * and 60% on a Project through June.
 */
function caraAt60Percent(): Employee
{
    $cara = Employee::factory()->create(['name' => 'Cara', 'start_date' => '2024-01-01']);
    Assignment::factory()->internal()->for($cara)->create();
    Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))
        ->create(['start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'share' => 60]);

    return $cara;
}

/**
 * A new Assignment form on a Project open since 2024.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newAssignment(array $overrides): array
{
    return [
        'project_id' => Project::factory()->create(['start_date' => '2024-01-01'])->id,
        'job_title' => 'Analyst',
        'start_date' => '2026-04-01',
        'end_date' => null,
        ...$overrides,
    ];
}

it('saves an Assignment that over-books the Employee, with a warning naming the first day and the total', function () {
    $cara = caraAt60Percent();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->post(route('employees.assignments.store', $cara), newAssignment(['share' => 50]))
        ->assertRedirect(route('employees.assignments.index', $cara))
        ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => 'Assignment created, but Cara is now Over-booked: their planned shares come to 110% on 1 Apr 2026.']);

    expect($cara->assignments()->where('job_title', 'Analyst')->exists())->toBeTrue();
});

it('saves without a warning when the planned shares stay within 100%', function (array $overrides) {
    $cara = caraAt60Percent();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->post(route('employees.assignments.store', $cara), newAssignment($overrides))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Assignment created.']);
})->with([
    'exactly 100%' => [['share' => 40]],
    'after the other one ends' => [['share' => 50, 'start_date' => '2026-07-01']],
]);

it('warns when a change to an Assignment over-books the Employee', function () {
    $cara = caraAt60Percent();
    $other = Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))
        ->create(['job_title' => 'Analyst', 'start_date' => '2026-05-01', 'end_date' => null, 'share' => 40]);

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $other), ['job_title' => 'Analyst', 'start_date' => '2026-05-01', 'end_date' => null, 'share' => 45])
        ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => 'Assignment saved, but Cara is now Over-booked: their planned shares come to 105% on 1 May 2026.']);
});

it('warns when planning an internal share over-books the Employee', function () {
    $cara = caraAt60Percent();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $cara->internalAssignment()->sole()), ['job_title' => null, 'share' => 50])
        ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => 'Assignment saved, but Cara is now Over-booked: their planned shares come to 110% on 1 Jan 2026.']);
});

it('flags an Employee on their Assignments page from the first day, today or later, they are Over-booked', function (string $start, ?string $end, ?string $flaggedFrom) {
    $cara = caraAt60Percent();
    Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))
        ->create(['start_date' => $start, 'end_date' => $end, 'share' => 50]);

    actingAs(employeeWith([Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('employees.assignments.index', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('overBookedFrom', $flaggedFrom));
})->with([
    'a clash still to come' => ['2026-05-01', null, '2026-05-01'],
    'a clash running today' => ['2026-02-01', null, '2026-03-01'],
    'a clash that is over' => ['2026-01-01', '2026-02-28', null],
    'no clash' => ['2026-07-01', null, null],
]);

it('flags Over-booked Employees on a Project\'s team and a Client\'s page', function () {
    $globex = Client::factory()->create();
    $project = Project::factory()->forClient($globex)->create(['start_date' => '2024-01-01']);
    $cara = caraAt60Percent();
    $dev = Employee::factory()->create(['name' => 'Dev', 'start_date' => '2024-01-01']);
    Assignment::factory()->for($cara)->for($project)->create(['start_date' => '2026-04-01', 'share' => 50]);
    Assignment::factory()->for($dev)->for($project)->create(['start_date' => '2026-04-01', 'share' => 100]);
    $viewer = employeeWith([Permission::ViewProjects->value => null, Permission::ManageClients->value => null, Permission::ViewAssignments->value => Scope::Everyone]);

    $flags = fn (Collection $assignments): array => $assignments->mapWithKeys(fn (array $assignment) => [$assignment['employee']['name'] => $assignment['employee']['is_over_booked']])->all();

    actingAs($viewer)->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('team', fn (Collection $team) => $flags($team) === ['Cara' => true, 'Dev' => false]));
    actingAs($viewer)->get(route('clients.show', $globex))
        ->assertInertia(fn (Assert $page) => $page->where('assignments', fn (Collection $assignments) => $flags($assignments) === ['Cara' => true, 'Dev' => false]));
});
