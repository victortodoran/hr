<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeInvitationController;
use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Notifications\Invitation;
use App\Policies\EmployeePolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class, EmployeeInvitationController::class, EmployeeRequest::class, EmployeePolicy::class, Employee::class);

beforeEach(fn () => Notification::fake());

it('shows an Employee\'s record on the edit page, without themselves as a possible Manager', function () {
    $ben = Employee::factory()->create(['name' => 'Ben Ford']);
    $cara = Employee::factory()->create(['name' => 'Cara Lane', 'manager_id' => $ben->id, 'start_date' => '2025-03-01']);

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $cara))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/Edit')
            ->where('employee', [
                'id' => $cara->id,
                'name' => 'Cara Lane',
                'email' => $cara->email,
                'department_id' => $cara->department_id,
                'manager_id' => $ben->id,
                'start_date' => '2025-03-01',
                'is_top' => false,
                'has_pending_invitation' => false,
            ])
            ->where('managers', fn (Collection $managers): bool => $managers->pluck('id')->contains($ben->id)
                && ! $managers->pluck('id')->contains($cara->id))
            ->has('departments'),
        );
});

it('lets HR edit an Employee\'s name, email, Department, Manager and Start Date', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);
    $finance = Department::factory()->create();
    $dana = Employee::factory()->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), [
            'name' => 'Cara Lane-Ford',
            'email' => 'cara.ford@acme.test',
            'department_id' => $finance->id,
            'manager_id' => $dana->id,
            'start_date' => '2024-01-15',
        ])
        ->assertRedirect(route('employees.edit', $cara))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Employee updated.']);

    $cara->refresh();

    expect($cara->name)->toBe('Cara Lane-Ford')
        ->and($cara->email)->toBe('cara.ford@acme.test')
        ->and($cara->department_id)->toBe($finance->id)
        ->and($cara->manager_id)->toBe($dana->id)
        ->and($cara->start_date->toDateString())->toBe('2024-01-15');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeRecordFields(Employee $employee, array $overrides = []): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'department_id' => $employee->department_id,
        'manager_id' => $employee->manager_id,
        'start_date' => $employee->start_date->toDateString(),
        ...$overrides,
    ];
}

it('keeps an Employee\'s own email valid when saving their record', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordFields($cara))
        ->assertSessionHasNoErrors();
});

it('refuses another Employee\'s email', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);
    $ben = Employee::factory()->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordFields($cara, ['email' => $ben->email]))
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

it('refuses making an Employee their own Manager', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordFields($cara, ['manager_id' => $cara->id]))
        ->assertSessionHasErrors(['manager_id' => 'An Employee cannot be their own Manager.']);
});

it('refuses removing the Manager of anyone but the top Employee', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordFields($cara, ['manager_id' => null]))
        ->assertSessionHasErrors(['manager_id' => 'Choose a Manager. Only the top Employee has none.']);
});

it('saves the top Employee without a Manager, and refuses giving them one', function () {
    $top = Employee::factory()->create(['is_top' => true]);
    $ben = Employee::factory()->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $top), employeeRecordFields($top, ['name' => 'Dana Owner']))
        ->assertSessionHasNoErrors();

    expect($top->refresh()->name)->toBe('Dana Owner');

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $top), employeeRecordFields($top, ['manager_id' => $ben->id]))
        ->assertSessionHasErrors(['manager_id' => 'The top Employee has no Manager.']);
});

it('resends the invitation to an Employee who has yet to set a password', function () {
    $cara = Employee::factory()->invited()->create();

    actingAs(employeeWhoManagesEmployees())
        ->post(route('employees.invitation.store', $cara))
        ->assertRedirect(route('employees.edit', $cara))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Invitation sent again to {$cara->email}."]);

    Notification::assertSentTo($cara, Invitation::class);
});

it('does not resend an invitation once the password is set', function () {
    $cara = Employee::factory()->create();

    actingAs(employeeWhoManagesEmployees())->post(route('employees.invitation.store', $cara))->assertForbidden();

    Notification::assertNothingSent();
});

it('refuses editing Employees, even yourself, without manage employees', function () {
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $cara = Employee::factory()->invited()->create(['manager_id' => $ben->id]);

    foreach ([$cara, $ben] as $employee) {
        actingAs($ben)->get(route('employees.edit', $employee))->assertForbidden();
        actingAs($ben)->patch(route('employees.update', $employee), employeeRecordFields($employee, ['name' => 'Changed']))->assertForbidden();
        expect($employee->refresh()->name)->not->toBe('Changed');
    }

    actingAs($ben)->post(route('employees.invitation.store', $cara))->assertForbidden();
    Notification::assertNothingSent();
});
