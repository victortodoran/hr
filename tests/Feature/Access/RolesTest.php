<?php

use App\Actions\ChangeAccess;
use App\Enums\AccessChange;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\RoleController;
use App\Http\Requests\RoleRequest;
use App\Models\AccessLogEntry;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeTime;

covers(RoleController::class, RoleRequest::class, ChangeAccess::class, Role::class, Permission::class, AccessLogEntry::class, Employee::class);

/**
 * An Employee who manages Roles, like the starter Admin.
 */
function roleManager(string $role = 'Admin'): Employee
{
    $admin = Employee::factory()->create(['name' => 'Ada Admin']);
    $admin->roles()->attach(Role::factory()->withPermissions([Permission::ManageRoles->value => null])->create(['name' => $role]));

    return $admin;
}

it('shows an Employee who manages Roles every Role with its Permissions, Scopes and holders', function () {
    $admin = roleManager();
    $payroll = Role::factory()->withPermissions([
        Permission::ViewPay->value => Scope::Everyone,
        Permission::ViewProjects->value => null,
    ])->create(['name' => 'Payroll']);
    $zoe = Employee::factory()->future()->create(['name' => 'Zoe Payroll']);
    $ben = Employee::factory()->create(['name' => 'Ben Payroll']);
    $payroll->employees()->attach([$zoe->id, $ben->id]);

    actingAs($admin)->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/Index')
            ->where('roles.1.name', 'Payroll')
            ->where('roles.1.id', $payroll->id)
            ->where('roles.1.permissions', ['view-pay' => 'everyone', 'view-projects' => null])
            ->where('roles.1.given_to_new_employees', false)
            ->where('roles.1.holders', [
                ['id' => $ben->id, 'name' => 'Ben Payroll', 'status' => 'current'],
                ['id' => $zoe->id, 'name' => 'Zoe Payroll', 'status' => 'future'],
            ])
            ->etc(),
        );
});

it('offers every Permission with the Scopes it can carry', function () {
    actingAs(roleManager())->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permissions', count(Permission::cases()))
            ->where('permissions.0', ['value' => 'view-employee-records', 'label' => 'View employee records', 'scopes' => ['own', 'reports', 'everyone']])
            ->where('permissions.6', ['value' => 'view-work-entries', 'label' => 'View work entries', 'scopes' => ['own', 'reports', 'everyone']])
            ->where('permissions.7', ['value' => 'manage-work-entries', 'label' => 'Manage work entries', 'scopes' => ['own', 'reports', 'everyone']])
            ->where('permissions.8', ['value' => 'approve-work-entries', 'label' => 'Approve work entries', 'scopes' => ['reports', 'everyone']])
            ->where('permissions.9', ['value' => 'reopen-work-entries', 'label' => 'Reopen work entries', 'scopes' => ['reports', 'everyone']])
            ->where('permissions.10', ['value' => 'lock-work-entries', 'label' => 'Lock work entries', 'scopes' => []])
            ->where('permissions.16', ['value' => 'manage-roles', 'label' => 'Manage roles', 'scopes' => []])
            ->etc(),
        );
});

it('refuses the Roles page to an Employee who does not manage Roles', function () {
    actingAs(employeeWith([Permission::ManageEmployees->value => null]))
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('lets an Employee who manages Roles create one, choosing Permissions and Scopes', function () {
    freezeTime();
    $admin = roleManager();

    actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'Senior Manager',
            'permissions' => ['view-pay' => 'reports', 'view-projects' => null],
            'given_to_new_employees' => true,
        ])
        ->assertRedirect(route('roles.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Role created.']);

    $role = Role::query()->where('name', 'Senior Manager')->sole();
    expect($role->grants())->toBe(['view-pay' => 'reports', 'view-projects' => null])
        ->and($role->is_given_to_new_employees)->toBeTrue();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->change)->toBe(AccessChange::RoleCreated)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->role_id)->toBe($role->id)
        ->and($entry->role_name)->toBe('Senior Manager')
        ->and($entry->employee_id)->toBeNull()
        ->and($entry->before)->toBeNull()
        ->and($entry->after)->toBe(['name' => 'Senior Manager', 'permissions' => ['view-pay' => 'reports', 'view-projects' => null], 'given_to_new_employees' => true])
        ->and($entry->created_at?->equalTo(now()->startOfSecond()))->toBeTrue();
});

it('rejects a Role whose name or Permissions are invalid', function (array $input, string $field, string $message) {
    actingAs(roleManager())
        ->post(route('roles.store'), ['name' => 'Payroll', 'permissions' => [], 'given_to_new_employees' => false, ...$input])
        ->assertSessionHasErrors([$field => $message]);

    expect(Role::query()->where('name', 'Payroll')->exists())->toBeFalse()
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
})->with([
    'missing name' => [['name' => ''], 'name', 'The name field is required.'],
    'name not text' => [['name' => ['Payroll']], 'name', 'The name field must be a string.'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name', 'The name field must not be greater than 255 characters.'],
    'name taken' => [['name' => 'Admin'], 'name', 'The name has already been taken.'],
    'permissions not a list' => [['permissions' => 'view-pay'], 'permissions', 'The permissions field must be an array.'],
    'unknown Permission' => [['permissions' => ['fly' => null]], 'permissions', 'The HR system has no Permission called "fly".'],
    'scoped Permission without a Scope' => [['permissions' => ['view-pay' => null]], 'permissions', 'Choose a Scope for View pay: own, reports or everyone.'],
    'scoped Permission with an unknown Scope' => [['permissions' => ['view-pay' => 'department']], 'permissions', 'Choose a Scope for View pay: own, reports or everyone.'],
    'approve work entries for own' => [['permissions' => ['approve-work-entries' => 'own']], 'permissions', 'Choose a Scope for Approve work entries: reports or everyone.'],
    'reopen work entries for own' => [['permissions' => ['reopen-work-entries' => 'own']], 'permissions', 'Choose a Scope for Reopen work entries: reports or everyone.'],
    'company-wide Permission with a Scope' => [['permissions' => ['manage-roles' => 'own']], 'permissions', 'Manage roles always applies company-wide, so it takes no Scope.'],
    'mark missing' => [['given_to_new_employees' => null], 'given_to_new_employees', 'The given to new employees field is required.'],
    'mark not true or false' => [['given_to_new_employees' => 'maybe'], 'given_to_new_employees', 'The given to new employees field must be true or false.'],
]);

it('requires the Permissions to be sent, even when the Role has none', function () {
    actingAs(roleManager())
        ->post(route('roles.store'), ['name' => 'Payroll', 'given_to_new_employees' => false])
        ->assertSessionHasErrors(['permissions' => 'The permissions field must be present.']);
});

it('lets an Employee who manages Roles rename a Role and change its Permissions, logging before and after', function () {
    $admin = roleManager();
    $manager = Role::factory()->withPermissions([
        Permission::ViewEmployeeRecords->value => Scope::Reports,
        Permission::ViewProjects->value => null,
    ])->create(['name' => 'Manager']);

    actingAs($admin)
        ->patch(route('roles.update', $manager), [
            'name' => 'Team Lead',
            'permissions' => ['view-employee-records' => 'everyone', 'view-pay' => 'reports'],
            'given_to_new_employees' => true,
        ])
        ->assertRedirect(route('roles.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Role saved.']);

    expect($manager->refresh()->name)->toBe('Team Lead')
        ->and($manager->load('permissions')->grants())->toBe(['view-employee-records' => 'everyone', 'view-pay' => 'reports'])
        ->and($manager->is_given_to_new_employees)->toBeTrue();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->change)->toBe(AccessChange::RoleEdited)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->role_id)->toBe($manager->id)
        ->and($entry->role_name)->toBe('Team Lead')
        ->and($entry->before)->toBe(['name' => 'Manager', 'permissions' => ['view-employee-records' => 'reports', 'view-projects' => null], 'given_to_new_employees' => false])
        ->and($entry->after)->toBe(['name' => 'Team Lead', 'permissions' => ['view-employee-records' => 'everyone', 'view-pay' => 'reports'], 'given_to_new_employees' => true]);
});

it('lets a Role keep its own name when saved', function () {
    $admin = roleManager();

    actingAs($admin)
        ->patch(route('roles.update', $admin->roles()->sole()), ['name' => 'Admin', 'permissions' => ['manage-roles' => null], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();
});

it('refuses to take manage roles out of the only Role that gives it to a current Employee', function () {
    $admin = roleManager();
    $role = $admin->roles()->sole();

    actingAs($admin)
        ->patch(route('roles.update', $role), ['name' => 'Admin', 'permissions' => ['manage-departments' => null], 'given_to_new_employees' => false])
        ->assertSessionHasErrors(['role' => 'Nobody else could manage Roles after this change, so it was refused. Give manage roles to another current Employee first.']);

    expect($role->load('permissions')->grants())->toBe(['manage-roles' => null])
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('lets manage roles leave a Role while another current Employee keeps it', function () {
    $admin = roleManager();
    roleManager('Second Admin');

    actingAs($admin)
        ->patch(route('roles.update', $admin->roles()->sole()), ['name' => 'Admin', 'permissions' => [], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();

    expect($admin->can(Permission::ManageRoles->value))->toBeFalse();
});

it('still allows changes that do not make it worse when nobody can manage Roles from now on', function () {
    $admin = roleManager();
    $admin->update(['leaving_date' => today()->addMonth()]);
    $payroll = Role::factory()->create(['name' => 'Payroll']);

    actingAs($admin)
        ->patch(route('roles.update', $payroll), ['name' => 'Payroll', 'permissions' => ['view-pay' => 'everyone'], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();

    expect($payroll->load('permissions')->grants())->toBe(['view-pay' => 'everyone']);
});

it('does not count an Employee who is leaving, has left or has yet to start as able to manage Roles', function (Closure $state) {
    $admin = roleManager();
    $other = roleManager('Second Admin');
    $other->update($state());

    actingAs($admin)
        ->patch(route('roles.update', $admin->roles()->sole()), ['name' => 'Admin', 'permissions' => [], 'given_to_new_employees' => false])
        ->assertSessionHasErrors('role');
})->with([
    'leaving' => [fn (): array => ['leaving_date' => today()->addWeek()]],
    'former' => [fn (): array => ['leaving_date' => today()->subDay()]],
    'future' => [fn (): array => ['start_date' => today()->addDay()]],
]);

it('lets an Employee who manages Roles delete a Role, logging what it held and who held it', function () {
    $admin = roleManager();
    $ben = Employee::factory()->create();
    $payroll = Role::factory()->withPermissions([Permission::ViewPay->value => Scope::Everyone])->create(['name' => 'Payroll']);
    $payroll->employees()->attach($ben);

    actingAs($admin)
        ->delete(route('roles.destroy', $payroll))
        ->assertRedirect(route('roles.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Role deleted.']);

    expect(Role::query()->whereKey($payroll->id)->exists())->toBeFalse()
        ->and($ben->can(Permission::ViewPay->value, $ben))->toBeFalse();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->change)->toBe(AccessChange::RoleDeleted)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->role_id)->toBeNull()
        ->and($entry->role_name)->toBe('Payroll')
        ->and($entry->before)->toBe(['name' => 'Payroll', 'permissions' => ['view-pay' => 'everyone'], 'given_to_new_employees' => false, 'holders' => [$ben->id]])
        ->and($entry->after)->toBeNull();
});

it('refuses to delete the only Role that lets a current Employee manage Roles', function () {
    $admin = roleManager();
    $role = $admin->roles()->sole();

    actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors(['role' => 'Nobody else could manage Roles after this change, so it was refused. Give manage roles to another current Employee first.']);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('gives new Employees the Roles marked for them on the Role form, so another Role can take over from the starter one', function () {
    $admin = roleManager();
    $admin->roles()->sole()->permissions()->create(['permission' => Permission::ManageEmployees, 'scope' => null]);
    $starter = Role::factory()->create(['name' => 'Employee', 'is_given_to_new_employees' => true]);
    $staff = Role::factory()->create(['name' => 'Staff']);
    Project::factory()->internal()->create();

    actingAs($admin)->patch(route('roles.update', $starter), ['name' => 'Employee', 'permissions' => [], 'given_to_new_employees' => false])->assertSessionHasNoErrors();
    actingAs($admin)->patch(route('roles.update', $staff), ['name' => 'Staff', 'permissions' => [], 'given_to_new_employees' => true])->assertSessionHasNoErrors();
    actingAs($admin)->post(route('employees.store'), [
        'name' => 'Cara Lane',
        'email' => 'cara@acme.test',
        'department_id' => $admin->department_id,
        'manager_id' => $admin->id,
        'start_date' => today()->toDateString(),
    ])->assertRedirect();

    expect(Employee::query()->where('email', 'cara@acme.test')->sole()->roles->pluck('name')->all())->toBe(['Staff']);
});

it('counts an Admin holder as able to manage Roles, so the last other Role that manages them can go', function () {
    $admin = roleManager('Role Managers');
    $roleManagers = $admin->roles()->sole();
    Employee::factory()->create()->roles()->attach(Role::factory()->admin()->create());

    actingAs($admin)
        ->delete(route('roles.destroy', $roleManagers))
        ->assertSessionHasNoErrors();

    expect(Role::query()->whereKey($roleManagers->id)->exists())->toBeFalse();
});

it('lets Admin be renamed and change who gets it, but keeps every Permission and logs the marker instead of grants', function () {
    $admin = roleManager();
    $adminRole = Role::factory()->admin()->create(['name' => 'Owners']);

    actingAs($admin)
        ->patch(route('roles.update', $adminRole), ['name' => 'Superusers', 'permissions' => ['view-pay' => 'own'], 'given_to_new_employees' => true])
        ->assertSessionHasNoErrors();

    expect($adminRole->refresh()->name)->toBe('Superusers')
        ->and($adminRole->is_given_to_new_employees)->toBeTrue()
        ->and($adminRole->permissions()->exists())->toBeFalse();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->before)->toBe(['name' => 'Owners', 'admin' => true, 'given_to_new_employees' => false])
        ->and($entry->after)->toBe(['name' => 'Superusers', 'admin' => true, 'given_to_new_employees' => true]);
});

it('refuses to delete Admin', function () {
    $admin = roleManager('Role Managers');
    $adminRole = Role::factory()->admin()->create();

    actingAs($admin)
        ->delete(route('roles.destroy', $adminRole))
        ->assertSessionHasErrors(['role' => 'Admin always holds every Permission, so it can\'t be deleted.']);

    expect(Role::query()->whereKey($adminRole->id)->exists())->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('shows Admin as holding every Permission with Scope everyone, marked so the editor can fix them', function () {
    $admin = roleManager('Role Managers');
    Role::factory()->admin()->create();

    actingAs($admin)->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles.0.name', 'Admin')
            ->where('roles.0.is_admin', true)
            ->has('roles.0.permissions', count(Permission::cases()))
            ->where('roles.0.permissions.view-employee-records', 'everyone')
            ->where('roles.0.permissions.manage-roles', null)
            ->where('roles.1.is_admin', false)
            ->etc(),
        );
});
