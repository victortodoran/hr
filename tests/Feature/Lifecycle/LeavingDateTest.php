<?php

use App\Enums\Permission;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeeRequest::class, EmployeePolicy::class, Employee::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function recordWith(Employee $employee, array $changes): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'department_id' => $employee->department_id,
        'manager_id' => $employee->manager_id,
        'start_date' => $employee->start_date->toDateString(),
        'leaving_date' => $employee->leaving_date?->toDateString(),
        ...$changes,
    ];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function reportingEmployee(array $attributes = []): Employee
{
    return Employee::factory()->create([
        'start_date' => '2024-05-01',
        'manager_id' => Employee::factory()->top()->create()->id,
        ...$attributes,
    ]);
}

it('lets HR set, move and clear a Leaving Date that has not passed', function (?string $from, ?string $to) {
    $cara = reportingEmployee(['leaving_date' => $from]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), recordWith($cara, ['leaving_date' => $to]))
        ->assertSessionHasNoErrors();

    expect($cara->refresh()->leaving_date?->toDateString())->toBe($to);
})->with([
    'set' => [null, '2026-04-30'],
    'move earlier' => ['2026-04-30', '2026-03-15'],
    'clear' => ['2026-04-30', null],
    'set to today' => [null, '2026-03-01'],
]);

it('lets HR record a leaving after the fact, making the Employee former straight away', function () {
    $cara = reportingEmployee();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), recordWith($cara, ['leaving_date' => '2026-02-27']))
        ->assertSessionHasNoErrors();

    expect($cara->refresh()->employmentStatus()->value)->toBe('former');
});

it('refuses changing or clearing a Leaving Date that has passed, but keeps the rest of the record editable', function () {
    $cara = reportingEmployee(['leaving_date' => '2026-02-27']);
    $hr = employeeWhoManagesEmployees();

    foreach (['2026-04-30', null] as $leavingDate) {
        actingAs($hr)
            ->patch(route('employees.update', $cara), recordWith($cara, ['leaving_date' => $leavingDate]))
            ->assertSessionHasErrors(['leaving_date' => 'A Leaving Date that has passed can\'t be changed.']);
    }

    actingAs($hr)
        ->patch(route('employees.update', $cara), recordWith($cara, ['name' => 'Cara Lane-Ford']))
        ->assertSessionHasNoErrors();

    expect($cara->refresh()->leaving_date?->toDateString())->toBe('2026-02-27')
        ->and($cara->name)->toBe('Cara Lane-Ford');
});

it('refuses a Leaving Date before the Start Date', function () {
    $cara = reportingEmployee();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), recordWith($cara, ['leaving_date' => '2024-04-30']))
        ->assertSessionHasErrors(['leaving_date' => 'The Leaving Date must be on or after the Start Date.']);
});

it('refuses a Leaving Date that is not a date', function () {
    $cara = reportingEmployee();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), recordWith($cara, ['leaving_date' => 'soon']))
        ->assertSessionHasErrors(['leaving_date' => 'The Leaving Date field must be a valid date.']);
});

it('refuses a Leaving Date for the top Employee until a new top Employee is named', function () {
    $top = Employee::factory()->top()->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $top), recordWith($top, ['leaving_date' => '2026-06-30']))
        ->assertSessionHasErrors(['leaving_date' => 'Name a new top Employee before the current one can leave.']);

    expect($top->refresh()->leaving_date)->toBeNull();
});

it('shows whether an Employee is future, current or former, with their Leaving Date', function (string $state, string $status) {
    $employee = Employee::factory()->{$state}()->create();

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $employee))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.status', $status)
            ->where('employee.leaving_date', $employee->leaving_date?->toDateString()),
        );
})->with([
    ['future', 'future'],
    ['former', 'former'],
]);

it('does not offer Former Employees as Managers, and marks Future ones', function () {
    $former = Employee::factory()->former()->create(['name' => 'Fay Former']);
    $future = Employee::factory()->future()->create(['name' => 'Finn Future']);
    $current = Employee::factory()->create(['name' => 'Cal Current']);

    actingAs(employeeWhoManagesEmployees())->get(route('employees.create'))
        ->assertInertia(fn (Assert $page) => $page->where('managers', fn (Collection $managers): bool => $managers->pluck('id')->doesntContain($former->id)
            && $managers->firstWhere('id', $future->id) === ['id' => $future->id, 'name' => 'Finn Future', 'status' => 'future']
            && $managers->firstWhere('id', $current->id) === ['id' => $current->id, 'name' => 'Cal Current', 'status' => 'current']));
});

it('keeps offering an Employee\'s current Manager after that Manager has left', function () {
    $ben = Employee::factory()->former()->create(['name' => 'Ben Ford']);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('managers', fn (Collection $managers): bool => $managers->firstWhere('id', $ben->id) === ['id' => $ben->id, 'name' => 'Ben Ford', 'status' => 'former']));
});

it('offers resending the invitation only while it can be resent', function (string $state, bool $canResend) {
    $cara = Employee::factory()->invited()->{$state}()->create();

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('employee.can_resend_invitation', $canResend));
})->with([
    'invited, not started yet' => ['future', true],
    'invited, but has left' => ['former', false],
]);

it('does not resend an invitation to a Former Employee', function () {
    $cara = Employee::factory()->invited()->former()->create();

    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.invitation.store', $cara))
        ->assertForbidden();
});

it('refuses a Leaving Date for the last Employee who could manage Roles, until someone else can', function () {
    $ada = reportingEmployee(['name' => 'Ada']);
    $adminRole = giveRole($ada, [Permission::ManageRoles->value => null, Permission::ManageEmployees->value => null]);

    actingAs($ada)
        ->patch(route('employees.update', $ada), recordWith($ada, ['leaving_date' => '2026-06-30']))
        ->assertSessionHasErrors(['leaving_date' => 'Ada is the last Employee who can manage Roles. Give manage roles to another current Employee before setting a Leaving Date.']);

    expect($ada->refresh()->leaving_date)->toBeNull();

    $adminRole->employees()->attach(Employee::factory()->create(['manager_id' => $ada->manager_id]));

    actingAs($ada)
        ->patch(route('employees.update', $ada), recordWith($ada, ['leaving_date' => '2026-06-30']))
        ->assertSessionHasNoErrors();

    expect($ada->refresh()->leaving_date?->toDateString())->toBe('2026-06-30');
});
