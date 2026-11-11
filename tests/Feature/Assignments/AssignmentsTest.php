<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\AssignmentController;
use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\AssignmentPolicy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, AssignmentRequest::class, AssignmentPolicy::class, Assignment::class, Employee::class, Project::class);

/**
 * An Employee who may put anyone on a Project, such as someone in HR.
 */
function assignmentManager(): Employee
{
    return employeeWith([Permission::ManageAssignments->value => Scope::Everyone]);
}

/**
 * A valid Assignment form, with any fields overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function assignmentForm(Project $project, array $overrides = []): array
{
    return [
        'project_id' => $project->id,
        'job_title' => 'Senior Developer',
        'start_date' => '2026-03-01',
        'end_date' => '2026-08-31',
        'capacity' => 60,
        ...$overrides,
    ];
}

it('lets an assignment manager put an Employee on a Project', function () {
    $ana = Employee::factory()->create(['start_date' => '2025-01-06']);
    $project = Project::factory()->create(['start_date' => '2026-01-05']);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $ana), assignmentForm($project))
        ->assertRedirect(route('employees.assignments.index', $ana))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Assignment created.']);

    $assignment = Assignment::query()->sole();

    expect($assignment->employee_id)->toBe($ana->id)
        ->and($assignment->project_id)->toBe($project->id)
        ->and($assignment->job_title)->toBe('Senior Developer')
        ->and($assignment->start_date->toDateString())->toBe('2026-03-01')
        ->and($assignment->end_date?->toDateString())->toBe('2026-08-31')
        ->and($assignment->capacity)->toBe(60);
});

it('refuses an Assignment outside the Employee\'s working period or the Project\'s dates', function (array $employee, array $project, array $overrides, string $field, string $message) {
    travelTo('2026-02-10');
    $ana = Employee::factory()->create(['name' => 'Ana', ...$employee]);
    $relaunch = Project::factory()->create(['name' => 'Relaunch', ...$project]);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $ana), assignmentForm($relaunch, $overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(Assignment::query()->count())->toBe(0);
})->with([
    'before the Employee\'s Start Date' => [['start_date' => '2026-03-02'], ['start_date' => '2026-01-05'], [], 'start_date', 'Ana\'s Start Date is 2 Mar 2026, so the Assignment can\'t start before it.'],
    'past the Employee\'s Leaving Date' => [['start_date' => '2025-01-06', 'leaving_date' => '2026-08-30'], ['start_date' => '2026-01-05'], [], 'end_date', 'Ana\'s Leaving Date is 30 Aug 2026, so the Assignment must end by then.'],
    'open-ended for an Employee who is leaving' => [['start_date' => '2025-01-06', 'leaving_date' => '2026-08-30'], ['start_date' => '2026-01-05'], ['end_date' => null], 'end_date', 'Ana\'s Leaving Date is 30 Aug 2026, so the Assignment must end by then.'],
    'before the Project starts' => [['start_date' => '2025-01-06'], ['start_date' => '2026-03-02'], [], 'start_date', 'Relaunch starts on 2 Mar 2026, so the Assignment can\'t start before it.'],
    'past the Project\'s end' => [['start_date' => '2025-01-06'], ['start_date' => '2026-01-05', 'end_date' => '2026-08-30'], [], 'end_date', 'Relaunch ends on 30 Aug 2026, so the Assignment must end by then.'],
    'open-ended on a Project that ends' => [['start_date' => '2025-01-06'], ['start_date' => '2026-01-05', 'end_date' => '2026-08-30'], ['end_date' => null], 'end_date', 'Relaunch ends on 30 Aug 2026, so the Assignment must end by then.'],
    'on a finished Project' => [['start_date' => '2025-01-06'], ['start_date' => '2025-06-01', 'end_date' => '2026-02-09'], ['start_date' => '2025-07-01', 'end_date' => '2025-12-31'], 'project_id', 'Relaunch has finished, so nobody can be assigned to it.'],
]);

it('accepts an Assignment that exactly fills the Employee\'s working period and the Project\'s dates', function () {
    travelTo('2026-02-10');
    $ana = Employee::factory()->create(['start_date' => '2026-03-01', 'leaving_date' => '2026-08-31']);
    $relaunch = Project::factory()->create(['start_date' => '2026-03-01', 'end_date' => '2026-08-31']);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $ana), assignmentForm($relaunch))
        ->assertSessionHasNoErrors();

    expect(Assignment::query()->count())->toBe(1);
});

it('lets a Future Employee be planned onto a Project from their Start Date', function () {
    travelTo('2026-02-10');
    $future = Employee::factory()->future()->create(['start_date' => '2026-03-01']);
    $relaunch = Project::factory()->create(['start_date' => '2026-01-05']);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $future), assignmentForm($relaunch, ['end_date' => null]))
        ->assertSessionHasNoErrors();

    expect($future->assignments()->sole()->start_date->toDateString())->toBe('2026-03-01');
});

it('accepts an Assignment on a Project that ends today', function () {
    travelTo('2026-02-10');
    $ana = Employee::factory()->create(['start_date' => '2025-01-06']);
    $relaunch = Project::factory()->create(['start_date' => '2025-06-01', 'end_date' => '2026-02-10']);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $ana), assignmentForm($relaunch, ['start_date' => '2026-01-01', 'end_date' => '2026-02-10']))
        ->assertSessionHasNoErrors();
});

it('rejects an invalid Assignment and explains why', function (Closure $overrides, string $field, string $message) {
    $ana = Employee::factory()->create(['start_date' => '2025-01-06']);
    $relaunch = Project::factory()->create(['start_date' => '2026-01-05']);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $ana), assignmentForm($relaunch, $overrides()))
        ->assertSessionHasErrors([$field => $message]);

    expect(Assignment::query()->count())->toBe(0);
})->with([
    'Project missing' => [fn (): array => ['project_id' => ''], 'project_id', 'The Project field is required.'],
    'unknown Project' => [fn (): array => ['project_id' => 999999], 'project_id', 'The selected Project is invalid.'],
    'Project not a number' => [fn (): array => ['project_id' => 'relaunch'], 'project_id', 'The Project field must be an integer.'],
    'the Internal Project' => [fn (): array => ['project_id' => Project::factory()->internal()->create()->id], 'project_id', 'The selected Project is invalid.'],
    'Job Title missing' => [fn (): array => ['job_title' => ''], 'job_title', 'The Job Title field is required.'],
    'Job Title not text' => [fn (): array => ['job_title' => ['x']], 'job_title', 'The Job Title field must be a string.'],
    'Job Title too long' => [fn (): array => ['job_title' => str_repeat('a', 256)], 'job_title', 'The Job Title field must not be greater than 255 characters.'],
    'start date missing' => [fn (): array => ['start_date' => ''], 'start_date', 'The start date field is required.'],
    'start date not a date' => [fn (): array => ['start_date' => 'soon'], 'start_date', 'The start date field must be a valid date.'],
    'end date not a date' => [fn (): array => ['end_date' => 'later'], 'end_date', 'The end date field must be a valid date.'],
    'end date before start' => [fn (): array => ['end_date' => '2026-02-28'], 'end_date', 'The end date field must be a date after or equal to start date.'],
    'Capacity missing' => [fn (): array => ['capacity' => ''], 'capacity', 'The Capacity field is required.'],
    'Capacity not whole' => [fn (): array => ['capacity' => 12.5], 'capacity', 'The Capacity field must be an integer.'],
    'Capacity below 0%' => [fn (): array => ['capacity' => -1], 'capacity', 'The Capacity field must be between 0 and 100.'],
    'Capacity above 100%' => [fn (): array => ['capacity' => 101], 'capacity', 'The Capacity field must be between 0 and 100.'],
]);

it('accepts a Capacity anywhere from 0% to 100%', function (int $capacity) {
    $ana = Employee::factory()->create(['start_date' => '2025-01-06']);
    $relaunch = Project::factory()->create(['start_date' => '2026-01-05']);

    actingAs(assignmentManager())
        ->post(route('employees.assignments.store', $ana), assignmentForm($relaunch, ['capacity' => $capacity]))
        ->assertSessionHasNoErrors();

    expect($ana->assignments()->sole()->capacity)->toBe($capacity);
})->with([0, 60, 100]);

it('lets an assignment manager change an Assignment\'s Job Title, dates and Capacity, but not its Project', function (int $capacity) {
    $ana = Employee::factory()->create(['start_date' => '2025-01-06']);
    $relaunch = Project::factory()->create(['start_date' => '2026-01-05']);
    $assignment = Assignment::factory()->for($ana)->for($relaunch)->create(['job_title' => 'Developer', 'start_date' => '2026-02-01', 'end_date' => null, 'capacity' => 50]);

    actingAs(assignmentManager())
        ->patch(route('assignments.update', $assignment), assignmentForm(Project::factory()->create(), ['job_title' => 'Tech Lead', 'start_date' => '2026-01-05', 'end_date' => '2026-12-31', 'capacity' => $capacity]))
        ->assertRedirect(route('employees.assignments.index', $ana))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Assignment saved.']);

    $assignment->refresh();

    expect($assignment->project_id)->toBe($relaunch->id)
        ->and($assignment->job_title)->toBe('Tech Lead')
        ->and($assignment->start_date->toDateString())->toBe('2026-01-05')
        ->and($assignment->end_date?->toDateString())->toBe('2026-12-31')
        ->and($assignment->capacity)->toBe($capacity);
})->with([0, 40, 100]);

it('lets an assignment manager end an Assignment early', function () {
    travelTo('2026-05-10');
    $assignment = Assignment::factory()
        ->for(Employee::factory()->create(['start_date' => '2025-01-06']))
        ->for(Project::factory()->create(['start_date' => '2026-01-05', 'end_date' => '2026-12-31']))
        ->create(['start_date' => '2026-01-05', 'end_date' => '2026-12-31']);

    actingAs(assignmentManager())
        ->patch(route('assignments.update', $assignment), assignmentForm($assignment->project, ['start_date' => '2026-01-05', 'end_date' => '2026-05-10']))
        ->assertSessionHasNoErrors();

    expect($assignment->refresh()->end_date?->toDateString())->toBe('2026-05-10');
});

it('keeps a changed Assignment within its Employee\'s and its Project\'s dates', function () {
    $ana = Employee::factory()->create(['name' => 'Ana', 'start_date' => '2025-01-06', 'leaving_date' => '2026-10-31']);
    $relaunch = Project::factory()->create(['name' => 'Relaunch', 'start_date' => '2026-01-05']);
    $assignment = Assignment::factory()->for($ana)->for($relaunch)->create(['start_date' => '2026-02-01', 'end_date' => '2026-06-30']);

    actingAs(assignmentManager())
        ->patch(route('assignments.update', $assignment), assignmentForm(Project::factory()->create(['start_date' => '2020-01-01']), ['start_date' => '2026-01-01', 'end_date' => '2026-11-30']))
        ->assertSessionHasErrors([
            'start_date' => 'Relaunch starts on 5 Jan 2026, so the Assignment can\'t start before it.',
            'end_date' => 'Ana\'s Leaving Date is 31 Oct 2026, so the Assignment must end by then.',
        ]);

    expect($assignment->refresh()->end_date?->toDateString())->toBe('2026-06-30');
});

it('lets an Assignment on a finished Project still be corrected', function () {
    travelTo('2026-05-10');
    $assignment = Assignment::factory()
        ->for(Employee::factory()->create(['start_date' => '2025-01-06']))
        ->for(Project::factory()->create(['start_date' => '2025-01-06', 'end_date' => '2025-12-31']))
        ->create(['job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31']);

    actingAs(assignmentManager())
        ->patch(route('assignments.update', $assignment), assignmentForm($assignment->project, ['job_title' => 'Senior Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31']))
        ->assertSessionHasNoErrors();

    expect($assignment->refresh()->job_title)->toBe('Senior Developer');
});
