<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Requests\Assignments\AssignmentRequest;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Rules\TravelRequestsFit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelRequestsFit::class, AssignmentRequest::class, EmployeeRequest::class, ProjectRequest::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

function relaunchWithTravel(?string $state = null): Assignment
{
    $relaunch = assignmentOf(Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'manager_id' => Employee::factory()->startedLongAgo()->create()->id]));
    $factory = TravelRequest::factory()->on($relaunch);
    ($state === null ? $factory : $factory->{$state}())->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);

    return $relaunch;
}

/** @return array<string, mixed> */
function endingAssignment(Assignment $assignment, string $endDate): array
{
    return ['job_title' => $assignment->job_title, 'start_date' => $assignment->start_date->toDateString(), 'end_date' => $endDate, 'capacity' => $assignment->capacity];
}

/** @return array<string, mixed> */
function startingAssignment(Assignment $assignment, string $startDate): array
{
    return ['job_title' => $assignment->job_title, 'start_date' => $startDate, 'end_date' => null, 'capacity' => $assignment->capacity];
}

it('refuses starting an Assignment after the first day of a Pending or Approved Travel Request on it', function (?string $state) {
    $relaunch = relaunchWithTravel($state);

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $relaunch), startingAssignment($relaunch, '2026-12-04'))
        ->assertInvalid(['start_date' => 'Cara has a Travel Request on this Assignment from 3 Dec 2026, so it can\'t start later than that.']);

    expect($relaunch->refresh()->start_date->toDateString())->toBe('2026-11-02');
})->with(['pending' => null, 'approved' => 'approved']);

it('lets an Assignment start on the first day of travel, or after Declined or Cancelled travel', function (?string $state, string $startDate) {
    $relaunch = relaunchWithTravel($state);

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $relaunch), startingAssignment($relaunch, $startDate))
        ->assertValid();

    expect($relaunch->refresh()->start_date->toDateString())->toBe($startDate);
})->with([
    'on the first day' => [null, '2026-12-03'],
    'after Declined travel' => ['declined', '2026-12-06'],
    'after Cancelled travel' => ['cancelled', '2026-12-06'],
]);

/** @return array<string, mixed> */
function endingProject(Assignment $assignment, string $endDate): array
{
    return ['name' => $assignment->project->name, 'client_id' => $assignment->project->client_id, 'start_date' => '2026-01-05', 'end_date' => $endDate];
}

it('refuses ending an Assignment before the last day of a Pending or Approved Travel Request on it', function (?string $state) {
    $relaunch = relaunchWithTravel($state);

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $relaunch), endingAssignment($relaunch, '2026-12-04'))
        ->assertInvalid(['end_date' => 'Cara has a Travel Request on this Assignment up to 5 Dec 2026, so it can\'t end earlier than that.']);

    expect($relaunch->refresh()->end_date)->toBeNull();
})->with(['pending' => null, 'approved' => 'approved']);

it('lets an Assignment end on the last day of travel, or before Declined or Cancelled travel', function (?string $state, string $endDate) {
    $relaunch = relaunchWithTravel($state);

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $relaunch), endingAssignment($relaunch, $endDate))
        ->assertValid();

    expect($relaunch->refresh()->end_date?->toDateString())->toBe($endDate);
})->with([
    'on the last day' => [null, '2026-12-05'],
    'before Declined travel' => ['declined', '2026-12-01'],
    'before Cancelled travel' => ['cancelled', '2026-12-01'],
]);

it('refuses a Leaving Date before the last day of the Employee\'s Pending or Approved travel, and allows one on it', function (?string $state) {
    $cara = relaunchWithTravel($state)->employee;

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-12-04']))
        ->assertInvalid(['leaving_date' => 'Cara has a Travel Request on Relaunch up to 5 Dec 2026, so their Leaving Date can\'t be earlier than that.']);

    expect($cara->refresh()->leaving_date)->toBeNull();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-12-05']))
        ->assertValid();

    expect($cara->refresh()->leaving_date?->toDateString())->toBe('2026-12-05');
})->with(['pending' => null, 'approved' => 'approved']);

function internalWithTravel(?string $state = null): Assignment
{
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'manager_id' => Employee::factory()->startedLongAgo()->create()->id]);
    $internal = Assignment::factory()->for($cara)->internal()->create(['start_date' => '2025-01-06']);
    $factory = TravelRequest::factory()->on($internal);
    ($state === null ? $factory : $factory->{$state}())->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);

    return $internal;
}

it('refuses a Start Date after the first day of the Employee\'s Pending or Approved travel on their Internal Assignment, and allows one on it', function (?string $state) {
    $internal = internalWithTravel($state);
    $cara = $internal->employee;

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-12-04']))
        ->assertInvalid(['start_date' => 'Cara has a Travel Request on '.$internal->project->name.' from 3 Dec 2026, so their Start Date can\'t be later than that.']);

    expect($cara->refresh()->start_date->toDateString())->toBe('2025-01-06');

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-12-03']))
        ->assertValid();

    expect($cara->refresh()->start_date->toDateString())->toBe('2026-12-03');
})->with(['pending' => null, 'approved' => 'approved']);

it('lets a Start Date move past Declined or Cancelled travel', function (string $state) {
    $cara = internalWithTravel($state)->employee;

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-12-06']))
        ->assertValid();

    expect($cara->refresh()->start_date->toDateString())->toBe('2026-12-06');
})->with(['declined', 'cancelled']);

it('refuses ending a Project before the last day of Pending or Approved travel on its Assignments, and allows it on that day', function (?string $state) {
    $relaunch = relaunchWithTravel($state);

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $relaunch->project), endingProject($relaunch, '2026-12-04'))
        ->assertInvalid(['end_date' => 'Cara has a Travel Request on Relaunch up to 5 Dec 2026, so it can\'t end earlier than that.']);

    expect($relaunch->project->refresh()->end_date)->toBeNull();

    actingAs(employeeWith([Permission::ManageProjects->value => null]))
        ->patch(route('projects.update', $relaunch->project), endingProject($relaunch, '2026-12-05'))
        ->assertValid();

    expect($relaunch->project->refresh()->end_date?->toDateString())->toBe('2026-12-05');
})->with(['pending' => null, 'approved' => 'approved']);
