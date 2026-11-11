<?php

use App\Actions\UpdateTrimmingAssignments;
use App\Enums\Permission;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProjectController;
use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ProjectRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Rules\AssignmentsFit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(UpdateTrimmingAssignments::class, EmployeeController::class, EmployeeRequest::class, ProjectController::class, ProjectRequest::class, AssignmentsFit::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * A Project as the edit form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function projectChanging(Project $project, array $changes): array
{
    return [
        'name' => $project->name,
        'client_id' => $project->client_id,
        'start_date' => $project->start_date?->toDateString(),
        'end_date' => $project->end_date?->toDateString(),
        ...$changes,
    ];
}

/**
 * An Employee working since 2024, reporting to the top Employee.
 *
 * @param  array<string, mixed>  $attributes
 */
function staffMember(array $attributes = []): Employee
{
    return Employee::factory()->create([
        'start_date' => '2024-01-01',
        'manager_id' => Employee::query()->where('is_top', true)->value('id') ?? Employee::factory()->top()->create()->id,
        ...$attributes,
    ]);
}

/**
 * Each Assignment's dates, earliest first, as "start – end".
 *
 * @return array<int, string>
 */
function periodsOf(Employee|Project $owner): array
{
    return $owner->assignments()->orderBy('start_date')->get()
        ->map(fn (Assignment $assignment): string => $assignment->start_date->toDateString().' – '.($assignment->end_date?->toDateString() ?? 'open'))
        ->all();
}

it('cuts the Employee\'s Assignments off at a Leaving Date being set or moved earlier', function (?string $from, string $to, array $expected) {
    $cara = staffMember(['leaving_date' => $from]);
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    Assignment::factory()->for($cara)->for($project)->createMany([
        ['start_date' => '2025-01-01', 'end_date' => '2025-12-31'],
        ['start_date' => '2026-01-01', 'end_date' => $from],
        ['start_date' => '2026-02-01', 'end_date' => '2026-03-20'],
    ]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => $to]))
        ->assertSessionHasNoErrors();

    expect(periodsOf($cara))->toBe($expected);
})->with([
    'set' => [null, '2026-03-15', ['2025-01-01 – 2025-12-31', '2026-01-01 – 2026-03-15', '2026-02-01 – 2026-03-15']],
    'moved earlier' => ['2026-06-30', '2026-03-15', ['2025-01-01 – 2025-12-31', '2026-01-01 – 2026-03-15', '2026-02-01 – 2026-03-15']],
    'on the day an Assignment ends' => [null, '2026-03-20', ['2025-01-01 – 2025-12-31', '2026-01-01 – 2026-03-20', '2026-02-01 – 2026-03-20']],
]);

it('leaves Assignments alone when a Leaving Date is moved later or cleared', function (?string $to) {
    $cara = staffMember(['leaving_date' => '2026-06-30']);
    Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))->create(['start_date' => '2026-01-01', 'end_date' => '2026-06-30']);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => $to]))
        ->assertSessionHasNoErrors();

    expect(periodsOf($cara))->toBe(['2026-01-01 – 2026-06-30']);
})->with(['moved later' => '2026-09-30', 'cleared' => null]);

it('refuses a Leaving Date before the start of one of the Employee\'s Assignments', function () {
    $cara = staffMember(['name' => 'Cara']);
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    Assignment::factory()->for($cara)->for($project)->createMany([
        ['start_date' => '2026-01-01', 'end_date' => null],
        ['start_date' => '2026-04-01', 'end_date' => null],
    ]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-03-31']))
        ->assertSessionHasErrors(['leaving_date' => 'Cara has an Assignment starting on 1 Apr 2026, so their Leaving Date can\'t be earlier than that.']);

    expect($cara->refresh()->leaving_date)->toBeNull()
        ->and(periodsOf($cara))->toBe(['2026-01-01 – open', '2026-04-01 – open']);
});

it('lets a Leaving Date fall on the day the Employee\'s last Assignment starts', function () {
    $cara = staffMember();
    Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))->create(['start_date' => '2026-03-31', 'end_date' => null]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-03-31']))
        ->assertSessionHasNoErrors();

    expect(periodsOf($cara))->toBe(['2026-03-31 – 2026-03-31']);
});

it('refuses a Project end date before the start of one of its Assignments', function () {
    $project = Project::factory()->create(['name' => 'Relaunch', 'start_date' => '2024-01-01']);
    Assignment::factory()->for(staffMember())->for($project)->createMany([
        ['start_date' => '2026-01-01', 'end_date' => null],
        ['start_date' => '2026-04-01', 'end_date' => null],
    ]);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), projectChanging($project, ['end_date' => '2026-03-31']))
        ->assertSessionHasErrors(['end_date' => 'Relaunch has an Assignment starting on 1 Apr 2026, so it can\'t end earlier than that.']);

    expect($project->refresh()->end_date)->toBeNull()
        ->and(periodsOf($project))->toBe(['2026-01-01 – open', '2026-04-01 – open']);
});

it('lets a Project end on the day its last Assignment starts', function () {
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    Assignment::factory()->for(staffMember())->for($project)->create(['start_date' => '2026-03-31', 'end_date' => null]);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), projectChanging($project, ['end_date' => '2026-03-31']))
        ->assertSessionHasNoErrors();

    expect(periodsOf($project))->toBe(['2026-03-31 – 2026-03-31']);
});

it('leaves other Employees\' Assignments alone when one Employee leaves', function () {
    $cara = staffMember();
    $dev = staffMember();
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    Assignment::factory()->for($cara)->for($project)->create(['start_date' => '2026-01-01', 'end_date' => null]);
    Assignment::factory()->for($dev)->for($project)->create(['start_date' => '2026-01-01', 'end_date' => null]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-03-31']))
        ->assertSessionHasNoErrors();

    expect(periodsOf($dev))->toBe(['2026-01-01 – open']);
});

it('cuts a Project\'s Assignments off at an end date being set or moved earlier, and leaves other Projects alone', function (?string $from) {
    $project = Project::factory()->create(['start_date' => '2024-01-01', 'end_date' => $from]);
    $other = Project::factory()->create(['start_date' => '2024-01-01']);
    $cara = staffMember();
    Assignment::factory()->for($cara)->for($project)->createMany([
        ['start_date' => '2025-01-01', 'end_date' => '2025-12-31'],
        ['start_date' => '2026-01-01', 'end_date' => $from],
    ]);
    Assignment::factory()->for($cara)->for($other)->create(['start_date' => '2026-01-01', 'end_date' => null]);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), projectChanging($project, ['end_date' => '2026-03-31']))
        ->assertSessionHasNoErrors();

    expect(periodsOf($project))->toBe(['2025-01-01 – 2025-12-31', '2026-01-01 – 2026-03-31'])
        ->and(periodsOf($other))->toBe(['2026-01-01 – open']);
})->with(['set' => null, 'moved earlier' => '2026-12-31']);

it('refuses to move an Employee\'s Start Date past the start of one of their Assignments', function () {
    $cara = staffMember(['name' => 'Cara', 'start_date' => '2026-01-01']);
    Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))->create(['start_date' => '2026-02-01']);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-02-02']))
        ->assertSessionHasErrors(['start_date' => 'Cara has an Assignment starting on 1 Feb 2026, so their Start Date can\'t be later than that.']);

    expect($cara->refresh()->start_date->toDateString())->toBe('2026-01-01');
});

it('lets an Employee\'s Start Date move up to the start of their first Assignment', function () {
    $cara = staffMember(['start_date' => '2026-01-01']);
    Assignment::factory()->for($cara)->for(Project::factory()->create(['start_date' => '2024-01-01']))->create(['start_date' => '2026-02-01']);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-02-01']))
        ->assertSessionHasNoErrors();
});

it('refuses to move a Project\'s start date past the start of one of its Assignments', function () {
    $project = Project::factory()->create(['name' => 'Relaunch', 'start_date' => '2026-01-01']);
    Assignment::factory()->for(staffMember())->for($project)->create(['start_date' => '2026-02-01']);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), projectChanging($project, ['start_date' => '2026-02-02']))
        ->assertSessionHasErrors(['start_date' => 'Relaunch has an Assignment starting on 1 Feb 2026, so it can\'t start later than that.']);

    expect($project->refresh()->start_date?->toDateString())->toBe('2026-01-01');
});

it('lets a Project\'s start date move up to the start of its first Assignment', function () {
    $project = Project::factory()->create(['start_date' => '2026-01-01']);
    Assignment::factory()->for(staffMember())->for($project)->create(['start_date' => '2026-02-01']);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), projectChanging($project, ['start_date' => '2026-02-01']))
        ->assertSessionHasNoErrors();
});

it('refuses to delete a Project that has Assignments', function () {
    $project = Project::factory()->create(['name' => 'Relaunch']);
    Assignment::factory()->for($project)->create();

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->delete(route('projects.destroy', $project))
        ->assertSessionHasErrors(['project' => 'Relaunch has Assignments, so it can\'t be deleted. Set an end date instead.']);

    expect(Project::query()->whereKey($project->id)->exists())->toBeTrue();
});

it('still explains an invalid date for an Employee or a Project with Assignments', function (string $employeeField, string $employeeMessage, string $projectField, string $projectMessage) {
    $cara = staffMember();
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    Assignment::factory()->for($cara)->for($project)->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, [$employeeField => 'soon']))
        ->assertSessionHasErrors([$employeeField => $employeeMessage]);
    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), projectChanging($project, [$projectField => 'soon']))
        ->assertSessionHasErrors([$projectField => $projectMessage]);
})->with([
    'start' => ['start_date', 'The Start Date field must be a valid date.', 'start_date', 'The start date field must be a valid date.'],
    'end' => ['leaving_date', 'The Leaving Date field must be a valid date.', 'end_date', 'The end date field must be a valid date.'],
]);
