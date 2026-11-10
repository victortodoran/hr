<?php

use App\Actions\AddEmployee;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Notifications\Invitation;
use App\Policies\EmployeePolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

covers(EmployeeController::class, EmployeeRequest::class, EmployeePolicy::class, AddEmployee::class, Employee::class);

beforeEach(fn () => Notification::fake());

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
        ->and($cara->is_top)->toBeFalse()
        ->and($cara->hasPendingInvitation())->toBeTrue();

    Notification::assertSentTo($cara, Invitation::class);
});

it('gives the new Employee every Role marked for new Employees, and no others', function () {
    $employeeRole = Role::factory()->create(['is_given_to_new_employees' => true]);
    $handbook = Role::factory()->create(['is_given_to_new_employees' => true]);
    Role::factory()->create(['is_given_to_new_employees' => false]);

    actingAs(employeeWhoManagesEmployees())->post(route('employees.store'), newEmployeeDetails());

    $cara = Employee::query()->where('email', 'cara@acme.test')->sole();

    expect($cara->roles->pluck('id')->sort()->values()->all())->toBe([$employeeRole->id, $handbook->id]);
});

it('lets the invited Employee set a password from the invite', function () {
    actingAs(employeeWhoManagesEmployees())->post(route('employees.store'), newEmployeeDetails());
    $cara = Employee::query()->where('email', 'cara@acme.test')->sole();

    $token = null;
    Notification::assertSentTo($cara, Invitation::class, function (Invitation $invitation) use (&$token) {
        $token = $invitation->token;

        return true;
    });

    auth()->logout();

    post(route('invitation.store'), [
        'token' => $token,
        'email' => 'cara@acme.test',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('login'));

    expect($cara->refresh()->hasPendingInvitation())->toBeFalse();
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
    'no Manager' => ['manager_id', '', 'Choose a Manager. Only the top Employee has none.'],
    'Manager not a number' => ['manager_id', 'ben', 'The Manager field must be an integer.'],
    'unknown Manager' => ['manager_id', 999999, 'The selected Manager is invalid.'],
    'no Start Date' => ['start_date', '', 'The Start Date field is required.'],
    'Start Date not a date' => ['start_date', 'next tuesday-ish', 'The Start Date field must be a valid date.'],
]);

it('refuses an email any Employee has ever used, including a Former Employee', function () {
    Employee::factory()->create(['email' => 'cara@acme.test']);

    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.store'), newEmployeeDetails())
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

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
