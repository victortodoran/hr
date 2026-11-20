<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Requests\Assignments\AssignmentRequest;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Rules\WorkEntriesFit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\travelTo;

covers(WorkEntriesFit::class, AssignmentRequest::class, EmployeeRequest::class, ProjectRequest::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

function caraWithWorkEntries(): Assignment
{
    $cara = recorder(['manager_id' => Employee::factory()->create()->id]);
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->createMany([['date' => '2026-11-03'], ['date' => '2026-11-10']]);

    return $relaunch;
}

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function assignmentChanging(Assignment $assignment, array $changes): array
{
    return [
        'job_title' => $assignment->job_title,
        'start_date' => $assignment->start_date->toDateString(),
        'end_date' => $assignment->end_date?->toDateString(),
        'capacity' => $assignment->capacity,
        ...$changes,
    ];
}

it('refuses to move an Assignment\'s dates past its Work Entries', function (string $field, string $date, string $message) {
    $relaunch = caraWithWorkEntries();

    $response = actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $relaunch), assignmentChanging($relaunch, [$field => $date]));

    $response->assertInvalid([$field => $message]);
    assertDatabaseHas('assignments', ['id' => $relaunch->id, 'start_date' => '2026-11-02', 'end_date' => null]);
})->with([
    'starting later' => ['start_date', '2026-11-04', 'Cara has Work Entries on this Assignment from 3 Nov 2026, so it can\'t start later than that.'],
    'ending earlier' => ['end_date', '2026-11-09', 'Cara has Work Entries on this Assignment up to 10 Nov 2026, so it can\'t end earlier than that.'],
]);

it('lets an Assignment\'s dates move up to its first and last Work Entries', function () {
    $relaunch = caraWithWorkEntries();

    $response = actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $relaunch), assignmentChanging($relaunch, ['start_date' => '2026-11-03', 'end_date' => '2026-11-10']));

    $response->assertValid();
    assertDatabaseHas('assignments', ['id' => $relaunch->id, 'start_date' => '2026-11-03', 'end_date' => '2026-11-10']);
});

it('refuses to move an Employee\'s dates past their Work Entries, which would cut off their Assignments', function (string $field, string $date, string $message) {
    $cara = caraWithWorkEntries()->employee;
    $internal = Assignment::factory()->for($cara)->internal()->create();
    WorkEntry::factory()->for($internal)->create(['date' => '2025-01-06']);

    $response = actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, [$field => $date]));

    $response->assertInvalid([$field => $message]);
    assertDatabaseHas('employees', ['id' => $cara->id, 'start_date' => '2025-01-06', 'leaving_date' => null]);
})->with([
    'Start Date later' => ['start_date', '2025-01-07', 'Cara has Work Entries from 6 Jan 2025, so their Start Date can\'t be later than that.'],
    'Leaving Date earlier' => ['leaving_date', '2026-11-09', 'Cara has Work Entries up to 10 Nov 2026, so their Leaving Date can\'t be earlier than that.'],
]);

it('lets an Employee leave on the day of their last Work Entry', function () {
    $cara = caraWithWorkEntries()->employee;

    $response = actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-11-10']));

    $response->assertValid();
    expect($cara->refresh()->leaving_date?->toDateString())->toBe('2026-11-10');
});

it('refuses to end a Project before the Work Entries on its Assignments', function () {
    $project = caraWithWorkEntries()->project;

    $response = actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), ['name' => 'Relaunch', 'client_id' => $project->client_id, 'start_date' => '2026-01-05', 'end_date' => '2026-11-09']);

    $response->assertInvalid(['end_date' => 'Relaunch has Work Entries up to 10 Nov 2026, so it can\'t end earlier than that.']);
    expect($project->refresh()->end_date)->toBeNull();
});

it('lets a Project end on the day of the last Work Entry on it', function () {
    $project = caraWithWorkEntries()->project;

    $response = actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $project), ['name' => 'Relaunch', 'client_id' => $project->client_id, 'start_date' => '2026-01-05', 'end_date' => '2026-11-10']);

    $response->assertValid();
    expect($project->refresh()->end_date?->toDateString())->toBe('2026-11-10');
});
