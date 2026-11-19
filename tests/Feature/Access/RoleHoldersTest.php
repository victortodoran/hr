<?php

use App\Actions\ChangeAccess;
use App\Enums\AccessChange;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Access\RoleHolderController;
use App\Http\Requests\Access\GiveRoleRequest;
use App\Models\AccessLogEntry;
use App\Models\Employee;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(RoleHolderController::class, GiveRoleRequest::class, RoleController::class, ChangeAccess::class, AccessLogEntry::class);

it('lets an Employee who manages Roles give a Role, and its Scope combines with those of the Roles already held', function () {
    $admin = employeeWhoManagesRoles();
    $ben = Employee::factory()->create();
    $cara = Employee::factory()->reportingTo(Employee::factory()->create())->create();
    giveRole($ben, [Permission::ViewPay->value => Scope::Own]);
    $payroll = Role::factory()->withPermissions([Permission::ViewPay->value => Scope::Everyone])->create(['name' => 'Payroll']);

    expect($ben->can(Permission::ViewPay->value, $cara))->toBeFalse();

    actingAs($admin)
        ->post(route('roles.holders.store', $payroll), ['employee_ids' => [$ben->id]])
        ->assertRedirect(route('roles.show', $payroll))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Payroll given to '.$ben->name.'.']);

    expect($ben->fresh()?->can(Permission::ViewPay->value, $cara))->toBeTrue();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->change)->toBe(AccessChange::RoleGiven)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->role_id)->toBe($payroll->id)
        ->and($entry->role_name)->toBe('Payroll')
        ->and($entry->employee_id)->toBe($ben->id)
        ->and($entry->before)->toBeNull()
        ->and($entry->after)->toBeNull();
});

it('lets an Employee who manages Roles give any Role to themselves', function () {
    $admin = employeeWhoManagesRoles();
    $payroll = Role::factory()->withPermissions([Permission::ManagePay->value => Scope::Everyone])->create();

    actingAs($admin)
        ->post(route('roles.holders.store', $payroll), ['employee_ids' => [$admin->id]])
        ->assertSessionHasNoErrors();

    expect($admin->can(Permission::ManagePay->value, Employee::factory()->create()))->toBeTrue();
});

it('refuses to give a Role to someone who already holds it, has left, or does not exist', function (Closure $employee, string $message) {
    $payroll = Role::factory()->create(['name' => 'Payroll']);

    actingAs(employeeWhoManagesRoles())
        ->post(route('roles.holders.store', $payroll), ['employee_ids' => [$employee($payroll)]])
        ->assertSessionHasErrors(['employee_ids.0' => $message]);

    expect(AccessLogEntry::query()->exists())->toBeFalse();
})->with([
    'already holds it' => [function (Role $role): int {
        $ben = Employee::factory()->create();
        $role->employees()->attach($ben);

        return $ben->id;
    }, 'They already hold this Role.'],
    'former' => [fn (Role $role): int => Employee::factory()->former()->create()->id, 'A Former Employee can\'t be given a Role.'],
    'not a number' => [fn (Role $role): string => 'ben', 'The Employee field must be an integer.'],
    'unknown' => [fn (Role $role): int => 999_999, 'The selected Employee is invalid.'],
]);

it('refuses to give a Role to nobody', function () {
    actingAs(employeeWhoManagesRoles())
        ->post(route('roles.holders.store', Role::factory()->create()), ['employee_ids' => []])
        ->assertSessionHasErrors(['employee_ids' => 'Choose someone to give the Role to.']);
});

it('gives a Role to several Employees at once, or to none of them when one can\'t have it', function () {
    $admin = employeeWhoManagesRoles();
    $payroll = Role::factory()->create(['name' => 'Payroll']);
    $ben = Employee::factory()->create();
    $zoe = Employee::factory()->future()->create();

    actingAs($admin)
        ->post(route('roles.holders.store', $payroll), ['employee_ids' => [$ben->id, $zoe->id]])
        ->assertRedirect(route('roles.show', $payroll))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Payroll given to 2 people.']);

    expect($payroll->employees()->count())->toBe(2)
        ->and(AccessLogEntry::query()->count())->toBe(2);

    actingAs($admin)
        ->post(route('roles.holders.store', $payroll), ['employee_ids' => [Employee::factory()->create()->id, Employee::factory()->former()->create()->id]])
        ->assertSessionHasErrors('employee_ids.1');

    expect($payroll->employees()->count())->toBe(2);
});

it('lets an Employee who manages Roles take a Role away', function () {
    $admin = employeeWhoManagesRoles();
    $ben = Employee::factory()->create();
    $payroll = giveRole($ben, [Permission::ViewPay->value => Scope::Everyone]);

    actingAs($admin)
        ->delete(route('roles.holders.destroy', [$payroll, $ben]))
        ->assertRedirect(route('roles.show', $payroll))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $payroll->name.' taken from '.$ben->name.'.']);

    expect($ben->can(Permission::ViewPay->value, $ben))->toBeFalse();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->change)->toBe(AccessChange::RoleTaken)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->role_id)->toBe($payroll->id)
        ->and($entry->employee_id)->toBe($ben->id);
});

it('refuses to take a Role from an Employee who does not hold it', function () {
    $payroll = Role::factory()->create();

    actingAs(employeeWhoManagesRoles())
        ->delete(route('roles.holders.destroy', [$payroll, Employee::factory()->create()]))
        ->assertNotFound();

    expect(AccessLogEntry::query()->exists())->toBeFalse();
});

it('refuses to take away the last current Employee\'s manage roles, even from themselves', function () {
    $admin = employeeWhoManagesRoles();
    $role = $admin->roles()->sole();

    actingAs($admin)
        ->delete(route('roles.holders.destroy', [$role, $admin]))
        ->assertSessionHasErrors(['role' => 'Nobody else could manage Roles after this change, so it was refused. Give manage roles to another current Employee first.']);

    expect($admin->can(Permission::ManageRoles->value))->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('refuses giving and taking Roles to an Employee who does not manage Roles', function (string $method, Closure $url) {
    $ben = Employee::factory()->create();
    $payroll = Role::factory()->create();

    actingAs(employeeWhoManagesEmployees())
        ->{$method}($url($payroll, $ben), ['employee_ids' => [$ben->id]])
        ->assertForbidden();
})->with([
    'give' => ['post', fn (Role $role, Employee $employee): string => route('roles.holders.store', $role)],
    'take' => ['delete', fn (Role $role, Employee $employee): string => route('roles.holders.destroy', [$role, $employee])],
]);

it('offers every Employee who has not left as someone to give Roles to', function () {
    $admin = employeeWhoManagesRoles();
    $future = Employee::factory()->future()->create(['name' => 'Zoe Future']);
    Employee::factory()->former()->create();

    actingAs($admin)->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees', [
                ['id' => $admin->id, 'name' => $admin->name, 'status' => 'current'],
                ['id' => $future->id, 'name' => 'Zoe Future', 'status' => 'future'],
            ])
            ->etc(),
        );
});
