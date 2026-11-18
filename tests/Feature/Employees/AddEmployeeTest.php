<?php

use App\Actions\AddEmployee;
use App\Actions\ChangeAccess;
use App\Enums\AccessChange;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\AccessLogEntry;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Notifications\Invitation;
use App\Policies\EmployeePolicy;
use App\Rules\AssignmentsFit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(Assignment::class, Project::class, EmployeeController::class, EmployeeRequest::class, EmployeePolicy::class, AddEmployee::class, ChangeAccess::class, Employee::class, AssignmentsFit::class);

beforeEach(function () {
    Notification::fake();
    Project::factory()->internal()->create(['name' => 'Acme Software']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newEmployeeDetails(array $overrides = []): array
{
    return [
        'name' => 'Cara Lane',
        'email' => 'cara@acme.test',
        'department_id' => Department::factory()->create(['name' => 'Finance'])->id,
        'manager_id' => Employee::factory()->create(['name' => 'Ben Ford'])->id,
        'start_date' => '2026-12-01',
        ...$overrides,
    ];
}

it('adds an Employee and invites them to set a password', function () {
    $details = newEmployeeDetails();

    $response = actingAs(employeeWhoManagesEmployees())->post(route('employees.store'), $details);

    $cara = Employee::query()->where('email', 'cara@acme.test')->sole();
    $response->assertRedirect(route('employees.edit', $cara))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Employee added. An invitation has been sent to cara@acme.test.']);

    expect($cara->name)->toBe('Cara Lane')
        ->and($cara->department_id)->toBe($details['department_id'])
        ->and($cara->manager_id)->toBe($details['manager_id'])
        ->and($cara->start_date->toDateString())->toBe('2026-12-01')
        ->and($cara->hasPendingInvitation())->toBeTrue();

    Notification::assertSentTo($cara, Invitation::class);
});

it('puts the new Employee on the Internal Project for their whole period of work, with no internal Job Title and 0% capacity', function (?string $leavingDate) {
    actingAs(employeeWhoManagesEmployees())->post(route('employees.store'), newEmployeeDetails(['leaving_date' => $leavingDate]));

    $internal = Employee::query()->where('email', 'cara@acme.test')->sole()->assignments()->sole();

    expect($internal->project->name)->toBe('Acme Software')
        ->and($internal->isInternal())->toBeTrue()
        ->and($internal->start_date->toDateString())->toBe('2026-12-01')
        ->and($internal->end_date?->toDateString())->toBe($leavingDate)
        ->and($internal->job_title)->toBeNull()
        ->and($internal->capacity)->toBe(0);
})->with(['staying' => null, 'leaving' => '2027-05-31']);

it('logs each Role given to a new Employee, with who added them', function () {
    $employeeRole = Role::factory()->create(['name' => 'Employee', 'is_given_to_new_employees' => true]);
    $hr = employeeWhoManagesEmployees();

    actingAs($hr)->post(route('employees.store'), newEmployeeDetails());

    $cara = Employee::query()->where('email', 'cara@acme.test')->sole();
    $entry = AccessLogEntry::query()->sole();

    expect($entry->change)->toBe(AccessChange::RoleGiven)
        ->and($entry->actor_id)->toBe($hr->id)
        ->and($entry->role_id)->toBe($employeeRole->id)
        ->and($entry->role_name)->toBe('Employee')
        ->and($entry->employee_id)->toBe($cara->id);
});

it('refuses an Employee record with a missing or invalid value, and says why', function (string $field, mixed $value, string $message) {
    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.store'), newEmployeeDetails([$field => $value]))
        ->assertSessionHasErrors([$field => $message]);

    expect(Employee::query()->where('name', 'Cara Lane')->exists())->toBeFalse();
})->with([
    'no name' => ['name', '', 'The name field is required.'],
    'name not text' => ['name', ['Cara'], 'The name field must be a string.'],
    'name too long' => ['name', str_repeat('a', 256), 'The name field must not be greater than 255 characters.'],
    'no email' => ['email', '', 'The email field is required.'],
    'email not text' => ['email', ['cara@acme.test'], 'The email field must be a string.'],
    'not an email' => ['email', 'cara-at-acme', 'The email field must be a valid email address.'],
    'email too long' => ['email', str_repeat('a', 250).'@acme.test', 'The email field must not be greater than 255 characters.'],
    'no Department' => ['department_id', '', 'The Department field is required.'],
    'Department not a number' => ['department_id', 'finance', 'The Department field must be an integer.'],
    'unknown Department' => ['department_id', 999999, 'The selected Department is invalid.'],
    'Manager not a number' => ['manager_id', 'ben', 'The Manager field must be an integer.'],
    'unknown Manager' => ['manager_id', 999999, 'The selected Manager is invalid.'],
    'no Start Date' => ['start_date', '', 'The Start Date field is required.'],
    'Start Date not a date' => ['start_date', 'next tuesday-ish', 'The Start Date field must be a valid date.'],
]);

it('shows the add page with the Departments and possible Managers to choose from', function () {
    $hr = employeeWhoManagesEmployees();
    $finance = Department::factory()->create(['name' => 'Finance']);
    $ben = Employee::factory()->create(['name' => 'Ben Ford']);

    actingAs($hr)->get(route('employees.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/Create')
            ->where('departments', fn (Collection $departments): bool => $departments->contains(fn (array $department): bool => $department === ['id' => $finance->id, 'name' => 'Finance']))
            ->where('managers', fn (Collection $managers): bool => $managers->contains(fn (array $manager): bool => $manager === ['id' => $ben->id, 'name' => 'Ben Ford', 'status' => 'current'])),
        );
});

it('refuses adding Employees to anyone without manage employees', function () {
    $manager = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);

    actingAs($manager)->get(route('employees.create'))->assertForbidden();
    actingAs($manager)->post(route('employees.store'), newEmployeeDetails())->assertForbidden();

    expect(Employee::query()->where('email', 'cara@acme.test')->exists())->toBeFalse();
});
