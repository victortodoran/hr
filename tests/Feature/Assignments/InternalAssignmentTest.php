<?php

use App\Actions\UpdateTrimmingAssignments;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\AssignmentController;
use App\Http\Requests\AssignmentRequest;
use App\Http\Requests\EmployeeRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Rules\AssignmentsFit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, AssignmentRequest::class, EmployeeRequest::class, UpdateTrimmingAssignments::class, AssignmentsFit::class, Assignment::class, Employee::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * An Employee working since 2024 with their Internal Assignment, reporting
 * to the top Employee.
 *
 * @param  array<string, mixed>  $attributes
 */
function internallyAssigned(array $attributes = []): Employee
{
    $employee = Employee::factory()->create([
        'start_date' => '2024-01-01',
        'manager_id' => Employee::factory()->top()->create()->id,
        ...$attributes,
    ]);
    Assignment::factory()->internal()->for($employee)->create();

    return $employee;
}

/**
 * The Internal Assignment's dates as "start – end".
 */
function internalPeriodOf(Employee $employee): string
{
    $internal = $employee->internalAssignment()->sole();

    return $internal->start_date->toDateString().' – '.($internal->end_date?->toDateString() ?? 'open');
}

it('keeps the Internal Assignment\'s dates on the Employee\'s Start Date and Leaving Date', function (array $from, array $to, string $expected) {
    $cara = internallyAssigned($from);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, $to))
        ->assertSessionHasNoErrors();

    expect(internalPeriodOf($cara))->toBe($expected);
})->with([
    'Start Date moved later' => [['start_date' => '2026-04-01'], ['start_date' => '2026-05-04'], '2026-05-04 – open'],
    'Start Date moved earlier' => [['start_date' => '2026-04-01'], ['start_date' => '2026-03-16'], '2026-03-16 – open'],
    'Leaving Date set' => [[], ['leaving_date' => '2026-06-30'], '2024-01-01 – 2026-06-30'],
    'Leaving Date moved later' => [['leaving_date' => '2026-06-30'], ['leaving_date' => '2026-09-30'], '2024-01-01 – 2026-09-30'],
    'Leaving Date cleared' => [['leaving_date' => '2026-06-30'], ['leaving_date' => null], '2024-01-01 – open'],
]);

it('lets an assignment manager set the internal Job Title and plan a share of time, or leave both empty', function (?string $jobTitle, ?int $share) {
    $cara = internallyAssigned();
    $internal = $cara->internalAssignment()->sole();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $internal), ['job_title' => $jobTitle, 'share' => $share])
        ->assertRedirect(route('employees.assignments.index', $cara))
        ->assertSessionHasNoErrors();

    expect($internal->refresh()->only('job_title', 'share'))->toBe(['job_title' => $jobTitle, 'share' => $share])
        ->and(internalPeriodOf($cara))->toBe('2024-01-01 – open');
})->with([
    'both' => ['Engineering Manager', 40],
    'Job Title only' => ['Engineering Manager', null],
    'neither' => [null, null],
]);

it('refuses to give the Internal Assignment dates of its own', function (string $field) {
    $cara = internallyAssigned(['name' => 'Cara']);
    $internal = $cara->internalAssignment()->sole();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $internal), ['job_title' => 'Designer', 'share' => null, $field => '2025-01-01'])
        ->assertSessionHasErrors([$field => 'The Internal Assignment always runs from Cara\'s Start Date to their Leaving Date.']);

    expect($internal->refresh()->job_title)->toBeNull();
})->with(['start_date', 'end_date']);

it('rejects an invalid internal Job Title or share of time and explains why', function (string $field, mixed $value, string $message) {
    $internal = internallyAssigned()->internalAssignment()->sole();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $internal), ['job_title' => 'Designer', 'share' => null, $field => $value])
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'Job Title not text' => ['job_title', ['Designer'], 'The Job Title field must be a string.'],
    'Job Title too long' => ['job_title', str_repeat('a', 256), 'The Job Title field must not be greater than 255 characters.'],
    'share zero' => ['share', 0, 'The share of time field must be between 1 and 100.'],
    'share over 100' => ['share', 101, 'The share of time field must be between 1 and 100.'],
    'share a fraction' => ['share', 40.5, 'The share of time field must be an integer.'],
]);

it('still requires a Job Title and a share of time on other Assignments', function () {
    $assignment = Assignment::factory()->for(internallyAssigned())->create();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->patch(route('assignments.update', $assignment), ['job_title' => null, 'start_date' => '2026-03-01', 'share' => null])
        ->assertSessionHasErrors(['job_title' => 'The Job Title field is required.', 'share' => 'The share of time field is required.']);
});

it('offers no way to delete the Internal Assignment', function () {
    $internal = internallyAssigned()->internalAssignment()->sole();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->delete(route('assignments.update', $internal))
        ->assertMethodNotAllowed();

    expect(Assignment::query()->whereKey($internal->id)->exists())->toBeTrue();
});
