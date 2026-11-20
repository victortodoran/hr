<?php

use App\Actions\Access\ChangeAccess;
use App\Actions\Employees\AddEmployee;
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
        'job_title' => 'Payroll Specialist',
        ...$overrides,
    ];
}

it('adds an Employee, invites them to set a password and lands on their Employee page', function () {
    $details = newEmployeeDetails();

    $hr = employeeWith([Permission::AddEmployees->value => null, Permission::ViewEmployeeRecords->value => Scope::Everyone]);

    $response = actingAs($hr)->post(route('employees.store'), $details);

    $cara = Employee::query()->where('email', 'cara@acme.test')->sole();
    $response->assertRedirect(route('employees.show', $cara))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Employee added. An invitation has been sent to cara@acme.test.']);

    expect($cara->name)->toBe('Cara Lane')
        ->and($cara->department_id)->toBe($details['department_id'])
        ->and($cara->manager_id)->toBe($details['manager_id'])
        ->and($cara->start_date->toDateString())->toBe('2026-12-01')
        ->and($cara->hasPendingInvitation())->toBeTrue();

    Notification::assertSentTo($cara, Invitation::class);
});

it('goes back to the add form after adding an Employee the actor may not see', function () {
    actingAs(employeeWhoManagesEmployees())->post(route('employees.store'), newEmployeeDetails())
        ->assertRedirect(route('employees.create'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Employee added. An invitation has been sent to cara@acme.test.']);
});

it('puts the new Employee on the Internal Project for their whole period of work, with the Job Title given and 0% capacity', function (?string $leavingDate) {
    actingAs(employeeWhoManagesEmployees())->post(route('employees.store'), newEmployeeDetails(['leaving_date' => $leavingDate]));

    $internal = Employee::query()->where('email', 'cara@acme.test')->sole()->assignments()->sole();

    expect($internal->project->name)->toBe('Acme Software')
        ->and($internal->isInternal())->toBeTrue()
        ->and($internal->start_date->toDateString())->toBe('2026-12-01')
        ->and($internal->end_date?->toDateString())->toBe($leavingDate)
        ->and($internal->job_title)->toBe('Payroll Specialist')
        ->and($internal->capacity)->toBe(0);
})->with(['staying' => null, 'leaving' => '2027-05-31']);

it('shows the new Employee\'s Job Title in the Directory', function () {
    $hr = employeeWith([Permission::AddEmployees->value => null, Permission::ViewDirectory->value => null]);
    actingAs($hr)->post(route('employees.store'), newEmployeeDetails(['start_date' => today()->toDateString()]));

    actingAs($hr)->get(route('directory.index', ['search' => 'Cara']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.job_title', 'Payroll Specialist'),
        );
});

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
    'no Start Date' => ['start_date', '', 'The Start Date field is required.'],
    'Start Date not a date' => ['start_date', 'next tuesday-ish', 'The Start Date field must be a valid date.'],
    'no Job Title' => ['job_title', '', 'The Job Title field is required.'],
    'Job Title not text' => ['job_title', ['Payroll Specialist'], 'The Job Title field must be a string.'],
    'Job Title too long' => ['job_title', str_repeat('a', 256), 'The Job Title field must not be greater than 255 characters.'],
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

it('adds an Employee for anyone with add employees, whatever their Scope for manage employees, landing where they may see the new Employee', function (array $grants, string $redirect) {
    $response = actingAs(employeeWith([Permission::AddEmployees->value => null, ...$grants]))->post(route('employees.store'), newEmployeeDetails());

    $cara = Employee::query()->where('email', 'cara@acme.test')->sole();
    $response->assertRedirect($redirect === 'show' ? route('employees.show', $cara) : route('employees.create'));
})->with([
    'manage employees for their Reports only' => [[Permission::ManageEmployees->value => Scope::Reports], 'create'],
    'manage employees and view records for everyone' => [[Permission::ManageEmployees->value => Scope::Everyone, Permission::ViewEmployeeRecords->value => Scope::Everyone], 'show'],
]);

it('refuses adding Employees to anyone without add employees, even if they manage every Employee', function (array $grants) {
    $manager = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, ...$grants]);

    actingAs($manager)->get(route('employees.create'))->assertForbidden();
    actingAs($manager)->post(route('employees.store'), newEmployeeDetails())->assertForbidden();

    expect(Employee::query()->where('email', 'cara@acme.test')->exists())->toBeFalse();
})->with([
    'view only' => [[]],
    'manage employees for everyone' => [[Permission::ManageEmployees->value => Scope::Everyone]],
]);
