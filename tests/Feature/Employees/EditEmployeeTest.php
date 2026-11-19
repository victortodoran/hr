<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeInvitationController;
use App\Http\Requests\Employees\EmployeeRequest;
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
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Lane', 'start_date' => '2025-03-01']);

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
                'leaving_date' => null,
                'status' => 'current',
                'can_resend_invitation' => false,
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

it('refuses making an Employee their own Manager', function () {
    $cara = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordFields($cara, ['manager_id' => $cara->id]))
        ->assertSessionHasErrors(['manager_id' => 'An Employee cannot be their own Manager.']);
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

/*
 * Dana (Finance, top) ── Ben ── Cara ── Dev
 */
it('lets an Employee edit only the Employees their Scope for manage employees covers', function (Scope $scope, array $editable) {
    $dana = Employee::factory()->invited()->create(['name' => 'Dana']);
    $ben = Employee::factory()->reportingTo($dana)->create(['name' => 'Ben']);
    $cara = Employee::factory()->invited()->reportingTo($ben)->create(['name' => 'Cara']);
    $dev = Employee::factory()->invited()->reportingTo($cara)->create(['name' => 'Dev']);
    giveRole($ben, [Permission::ManageEmployees->value => $scope]);

    foreach ([$dana, $ben, $cara, $dev] as $employee) {
        $allowed = in_array($employee->name, $editable, true);
        $name = $employee->name;

        actingAs($ben)->get(route('employees.edit', $employee))->assertStatus($allowed ? 200 : 403);
        actingAs($ben)->patch(route('employees.update', $employee), employeeRecordFields($employee, ['name' => "{$name} changed"]))->assertStatus($allowed ? 302 : 403);
        expect($employee->refresh()->name)->toBe($allowed ? "{$name} changed" : $name);
    }
})->with([
    'own' => [Scope::Own, ['Ben']],
    'reports' => [Scope::Reports, ['Cara', 'Dev']],
    'everyone' => [Scope::Everyone, ['Dana', 'Ben', 'Cara', 'Dev']],
]);

it('opens the Employee\'s page after a change that takes them out of what the viewer may edit', function () {
    $ben = Employee::factory()->create();
    $cara = Employee::factory()->reportingTo($ben)->create();
    $dana = Employee::factory()->create();
    giveRole($ben, [Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::ManageEmployees->value => Scope::Reports]);

    actingAs($ben)->patch(route('employees.update', $cara), employeeRecordFields($cara, ['manager_id' => $dana->id]))
        ->assertRedirect(route('employees.show', $cara));

    expect($cara->refresh()->manager_id)->toBe($dana->id);
});

it('resends invitations only to Employees the viewer may edit', function () {
    $dana = Employee::factory()->invited()->create();
    $ben = Employee::factory()->reportingTo($dana)->create();
    $cara = Employee::factory()->invited()->reportingTo($ben)->create();
    giveRole($ben, [Permission::ManageEmployees->value => Scope::Reports]);

    actingAs($ben)->post(route('employees.invitation.store', $dana))->assertForbidden();
    actingAs($ben)->post(route('employees.invitation.store', $cara))->assertRedirect(route('employees.edit', $cara));

    Notification::assertSentTo($cara, Invitation::class);
    Notification::assertNotSentTo($dana, Invitation::class);
});

it('refuses editing Employees, even yourself, without manage employees', function () {
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $cara = Employee::factory()->invited()->reportingTo($ben)->create();

    foreach ([$cara, $ben] as $employee) {
        actingAs($ben)->get(route('employees.edit', $employee))->assertForbidden();
        actingAs($ben)->patch(route('employees.update', $employee), employeeRecordFields($employee, ['name' => 'Changed']))->assertForbidden();
        expect($employee->refresh()->name)->not->toBe('Changed');
    }

    actingAs($ben)->post(route('employees.invitation.store', $cara))->assertForbidden();
    Notification::assertNothingSent();
});
