<?php

use App\Actions\Access\ChangeAccess;
use App\Enums\AccessChange;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Access\RoleController;
use App\Http\Requests\Access\RoleRequest;
use App\Models\AccessLogEntry;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeTime;

covers(RoleController::class, RoleRequest::class, ChangeAccess::class, Role::class, Permission::class, AccessLogEntry::class, Employee::class);

it('shows an Employee who manages Roles every Role with its Permissions, Scopes and holders', function () {
    $admin = employeeWhoManagesRoles();
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

it('offers every Permission with the Scopes it can carry, in its group', function () {
    actingAs(employeeWhoManagesRoles())->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permissions', 23)
            ->where('permissions.0', ['value' => 'view-employee-records', 'label' => 'View Employee records', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Employees'])
            ->where('permissions.1', ['value' => 'add-employees', 'label' => 'Add Employees', 'scopes' => [], 'group' => 'Employees'])
            ->where('permissions.2', ['value' => 'manage-employees', 'label' => 'Manage Employees', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Employees'])
            ->where('permissions.7', ['value' => 'view-work-entries', 'label' => 'View Work Entries', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Work Entries'])
            ->where('permissions.8', ['value' => 'manage-work-entries', 'label' => 'Manage Work Entries', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Work Entries'])
            ->where('permissions.9', ['value' => 'reopen-work-entries', 'label' => 'Reopen Work Entries', 'scopes' => ['reports', 'everyone'], 'group' => 'Work Entries'])
            ->where('permissions.10', ['value' => 'view-time-off', 'label' => 'View Time Off', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Time Off'])
            ->where('permissions.11', ['value' => 'manage-paid-days', 'label' => 'Manage Paid Days', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Time Off'])
            ->where('permissions.12', ['value' => 'view-travel', 'label' => 'View travel', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Travel'])
            ->where('permissions.13', ['value' => 'reopen-travel-expenses', 'label' => 'Reopen Travel Expenses', 'scopes' => ['reports', 'everyone'], 'group' => 'Travel'])
            ->where('permissions.14', ['value' => 'view-performance-evaluations', 'label' => 'View Performance Evaluations', 'scopes' => ['own', 'reports', 'everyone'], 'group' => 'Performance'])
            ->where('permissions.15', ['value' => 'reopen-performance-evaluations', 'label' => 'Reopen Performance Evaluations', 'scopes' => ['reports', 'everyone'], 'group' => 'Performance'])
            ->where('permissions.21', ['value' => 'manage-roles', 'label' => 'Manage Roles', 'scopes' => [], 'group' => 'Administration'])
            ->where('permissions', fn (Collection $permissions): bool => $permissions->pluck('group')->unique()->values()->all() === ['Employees', 'Assignments', 'Pay', 'Work Entries', 'Time Off', 'Travel', 'Performance', 'Organization', 'Administration'])
            ->etc(),
        );
});

it('refuses every Roles page to an Employee who does not manage Roles', function (Closure $url) {
    $role = Role::factory()->create();

    actingAs(employeeWhoManagesEmployees())
        ->get($url($role))
        ->assertForbidden();
})->with([
    'the list' => [fn (Role $role) => route('roles.index')],
    'one Role' => [fn (Role $role) => route('roles.show', $role)],
    'a new Role' => [fn (Role $role) => route('roles.create')],
]);

it('opens the Roles page on the first Role by name, or on the Role in the URL, to view and edit in place, or create', function (Closure $url, string $mode, ?string $selected) {
    $admin = employeeWhoManagesRoles('Role Managers');
    $roles = [
        'Auditor' => Role::factory()->create(['name' => 'Auditor']),
        'Payroll' => Role::factory()->create(['name' => 'Payroll']),
    ];

    actingAs($admin)->get($url($roles))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/Index')
            ->where('mode', $mode)
            ->where('selected', $selected === null ? null : $roles[$selected]->id)
            ->etc(),
        );
})->with([
    'the list' => [fn (array $roles) => route('roles.index'), 'view', 'Auditor'],
    'one Role' => [fn (array $roles) => route('roles.show', $roles['Payroll']), 'view', 'Payroll'],
    'a new Role' => [fn (array $roles) => route('roles.create'), 'create', null],
]);

it('lets an Employee who manages Roles create one, choosing Permissions and Scopes', function () {
    freezeTime();
    $admin = employeeWhoManagesRoles();

    actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'Senior Manager',
            'permissions' => ['view-pay' => 'reports', 'view-projects' => null],
            'given_to_new_employees' => true,
        ])
        ->assertRedirect(route('roles.show', Role::query()->where('name', 'Senior Manager')->value('id')))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Senior Manager created']);

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
    actingAs(employeeWhoManagesRoles())
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
    'scoped Permission without a Scope' => [['permissions' => ['view-pay' => null]], 'permissions', 'Choose a Scope for View Pay: own, reports or everyone.'],
    'scoped Permission with an unknown Scope' => [['permissions' => ['view-pay' => 'department']], 'permissions', 'Choose a Scope for View Pay: own, reports or everyone.'],
    'reopen work entries for own' => [['permissions' => ['reopen-work-entries' => 'own']], 'permissions', 'Choose a Scope for Reopen Work Entries: reports or everyone.'],
    'reopen travel expenses for own' => [['permissions' => ['reopen-travel-expenses' => 'own']], 'permissions', 'Choose a Scope for Reopen Travel Expenses: reports or everyone.'],
    'company-wide Permission with a Scope' => [['permissions' => ['manage-roles' => 'own']], 'permissions', 'Manage Roles always applies company-wide, so it takes no Scope.'],
    'mark missing' => [['given_to_new_employees' => null], 'given_to_new_employees', 'The given to new employees field is required.'],
    'mark not true or false' => [['given_to_new_employees' => 'maybe'], 'given_to_new_employees', 'The given to new employees field must be true or false.'],
]);

it('requires the Permissions to be sent, even when the Role has none', function () {
    actingAs(employeeWhoManagesRoles())
        ->post(route('roles.store'), ['name' => 'Payroll', 'given_to_new_employees' => false])
        ->assertSessionHasErrors(['permissions' => 'The permissions field must be present.']);
});

it('lets an Employee who manages Roles rename a Role and change its Permissions, logging before and after', function () {
    $admin = employeeWhoManagesRoles();
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
        ->assertRedirect(route('roles.show', $manager))
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
    $admin = employeeWhoManagesRoles();

    actingAs($admin)
        ->patch(route('roles.update', $admin->roles()->sole()), ['name' => 'Admin', 'permissions' => ['manage-roles' => null], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();
});

it('refuses to take manage roles out of the only Role that gives it to a current Employee', function () {
    $admin = employeeWhoManagesRoles();
    $role = $admin->roles()->sole();

    actingAs($admin)
        ->patch(route('roles.update', $role), ['name' => 'Admin', 'permissions' => ['manage-departments' => null], 'given_to_new_employees' => false])
        ->assertSessionHasErrors(['role' => 'Nobody else could manage Roles after this change, so it was refused. Give manage roles to another current Employee first.']);

    expect($role->load('permissions')->grants())->toBe(['manage-roles' => null])
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('lets manage roles leave a Role while another current Employee keeps it', function () {
    $admin = employeeWhoManagesRoles();
    employeeWhoManagesRoles('Second Admin');

    actingAs($admin)
        ->patch(route('roles.update', $admin->roles()->sole()), ['name' => 'Admin', 'permissions' => [], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();

    expect($admin->can(Permission::ManageRoles->value))->toBeFalse();
});

it('still allows changes that do not make it worse when nobody can manage Roles from now on', function () {
    $admin = employeeWhoManagesRoles();
    $admin->update(['leaving_date' => today()->addMonth()]);
    $payroll = Role::factory()->create(['name' => 'Payroll']);

    actingAs($admin)
        ->patch(route('roles.update', $payroll), ['name' => 'Payroll', 'permissions' => ['view-pay' => 'everyone'], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();

    expect($payroll->load('permissions')->grants())->toBe(['view-pay' => 'everyone']);
});

it('does not count an Employee who is leaving, has left or has yet to start as able to manage Roles', function (Closure $state) {
    $admin = employeeWhoManagesRoles();
    $other = employeeWhoManagesRoles('Second Admin');
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
    $admin = employeeWhoManagesRoles();
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
    $admin = employeeWhoManagesRoles();
    $role = $admin->roles()->sole();

    actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors(['role' => 'Nobody else could manage Roles after this change, so it was refused. Give manage roles to another current Employee first.']);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('gives new Employees the Role marked for them on the Role form, so another Role can take over from the starter one', function () {
    $admin = employeeWhoManagesRoles();
    $admin->roles()->sole()->permissions()->create(['permission' => Permission::AddEmployees, 'scope' => null]);
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
        'job_title' => 'Developer',
    ])->assertRedirect();

    expect(Employee::query()->where('email', 'cara@acme.test')->sole()->roles->pluck('name')->all())->toBe(['Staff']);
});

it('gives new Employees at most one Role: turning it on for a Role takes it off the previous one, logged, and turning it off leaves none', function (Closure $save) {
    $admin = employeeWhoManagesRoles();
    $starter = Role::factory()->create(['name' => 'Employee', 'is_given_to_new_employees' => true]);

    $taker = $save($admin);

    expect($starter->refresh()->is_given_to_new_employees)->toBeFalse()
        ->and(Role::query()->where('is_given_to_new_employees', true)->pluck('id')->all())->toBe([$taker->id]);
    $logged = AccessLogEntry::query()->where('role_id', $starter->id)->sole();
    expect($logged->change)->toBe(AccessChange::RoleEdited)
        ->and($logged->before)->toMatchArray(['given_to_new_employees' => true])
        ->and($logged->after)->toMatchArray(['given_to_new_employees' => false]);

    actingAs($admin)->patch(route('roles.update', $taker), ['name' => $taker->name, 'permissions' => [], 'given_to_new_employees' => false])->assertSessionHasNoErrors();

    expect(Role::query()->where('is_given_to_new_employees', true)->exists())->toBeFalse();
})->with([
    'a new Role' => [function (Employee $admin): Role {
        actingAs($admin)->post(route('roles.store'), ['name' => 'Staff', 'permissions' => [], 'given_to_new_employees' => true])->assertSessionHasNoErrors();

        return Role::query()->where('name', 'Staff')->sole();
    }],
    'an edited Role' => [function (Employee $admin): Role {
        $staff = Role::factory()->create(['name' => 'Staff']);
        actingAs($admin)->patch(route('roles.update', $staff), ['name' => 'Staff', 'permissions' => [], 'given_to_new_employees' => true])->assertSessionHasNoErrors();

        return $staff;
    }],
]);

it('gives and takes a Role from the holders sent with it, logging each, when creating or editing it', function () {
    $admin = employeeWhoManagesRoles();
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $zoe = Employee::factory()->future()->create(['name' => 'Zoe']);
    $leftWhileHolding = Employee::factory()->former()->create(['name' => 'Lea']);

    actingAs($admin)->post(route('roles.store'), ['name' => 'Payroll', 'permissions' => [], 'given_to_new_employees' => false, 'holders' => [$ben->id, $cara->id]])
        ->assertSessionHasNoErrors();
    $payroll = Role::query()->where('name', 'Payroll')->sole();
    $payroll->employees()->attach($leftWhileHolding);

    expect(AccessLogEntry::query()->where('change', AccessChange::RoleGiven)->orderBy('employee_id')->pluck('employee_id')->all())->toBe([$ben->id, $cara->id]);

    actingAs($admin)->patch(route('roles.update', $payroll), ['name' => 'Payroll', 'permissions' => [], 'given_to_new_employees' => false, 'holders' => [$ben->id, $zoe->id, $leftWhileHolding->id]])
        ->assertSessionHasNoErrors();

    expect($payroll->employees()->orderBy('name')->pluck('name')->all())->toBe(['Ben', 'Lea', 'Zoe'])
        ->and(AccessLogEntry::query()->where('change', AccessChange::RoleTaken)->pluck('employee_id')->all())->toBe([$cara->id])
        ->and(AccessLogEntry::query()->where('change', AccessChange::RoleGiven)->where('employee_id', $zoe->id)->exists())->toBeTrue();
});

it('leaves who holds a Role alone when no holders are sent with it', function () {
    $admin = employeeWhoManagesRoles();
    $payroll = Role::factory()->create(['name' => 'Payroll']);
    $payroll->employees()->attach($ben = Employee::factory()->create());

    actingAs($admin)->patch(route('roles.update', $payroll), ['name' => 'Payroll', 'permissions' => [], 'given_to_new_employees' => false])
        ->assertSessionHasNoErrors();

    expect($payroll->employees()->pluck('employees.id')->all())->toBe([$ben->id]);
});

it('refuses holders who have left or do not exist, saving nothing', function (Closure $holder) {
    $admin = employeeWhoManagesRoles();
    $payroll = Role::factory()->create(['name' => 'Payroll']);

    actingAs($admin)->patch(route('roles.update', $payroll), ['name' => 'Pay Team', 'permissions' => [], 'given_to_new_employees' => false, 'holders' => [$holder()]])
        ->assertSessionHasErrors('holders.0');

    expect($payroll->refresh()->name)->toBe('Payroll')
        ->and($payroll->employees()->exists())->toBeFalse();
})->with([
    'a Former Employee' => [fn () => Employee::factory()->former()->create()->id],
    'nobody' => [fn () => 999999],
]);

it('saves nothing when the holders sent would leave nobody able to manage Roles', function () {
    $admin = employeeWhoManagesRoles('Role Managers');
    $roleManagers = $admin->roles()->sole();

    actingAs($admin)->patch(route('roles.update', $roleManagers), ['name' => 'Renamed', 'permissions' => ['manage-roles' => null], 'given_to_new_employees' => false, 'holders' => []])
        ->assertSessionHasErrors('role');

    expect($roleManagers->refresh()->name)->toBe('Role Managers')
        ->and($roleManagers->employees()->pluck('employees.id')->all())->toBe([$admin->id])
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('counts an Admin holder as able to manage Roles, so the last other Role that manages them can go', function () {
    $admin = employeeWhoManagesRoles('Role Managers');
    $roleManagers = $admin->roles()->sole();
    Employee::factory()->create()->roles()->attach(Role::factory()->admin()->create());

    actingAs($admin)
        ->delete(route('roles.destroy', $roleManagers))
        ->assertSessionHasNoErrors();

    expect(Role::query()->whereKey($roleManagers->id)->exists())->toBeFalse();
});

it('lets Admin be renamed, but keeps every Permission, never gives it to new Employees, and logs the marker instead of grants', function () {
    $admin = employeeWhoManagesRoles();
    $adminRole = Role::factory()->admin()->create(['name' => 'Owners']);

    actingAs($admin)
        ->patch(route('roles.update', $adminRole), ['name' => 'Superusers', 'permissions' => ['view-pay' => 'own'], 'given_to_new_employees' => true])
        ->assertSessionHasNoErrors();

    expect($adminRole->refresh()->name)->toBe('Superusers')
        ->and($adminRole->is_given_to_new_employees)->toBeFalse()
        ->and($adminRole->permissions()->exists())->toBeFalse();

    $entry = AccessLogEntry::query()->sole();
    expect($entry->before)->toBe(['name' => 'Owners', 'admin' => true, 'given_to_new_employees' => false])
        ->and($entry->after)->toBe(['name' => 'Superusers', 'admin' => true, 'given_to_new_employees' => false]);
});

it('refuses to delete Admin', function () {
    $admin = employeeWhoManagesRoles('Role Managers');
    $adminRole = Role::factory()->admin()->create();

    actingAs($admin)
        ->delete(route('roles.destroy', $adminRole))
        ->assertSessionHasErrors(['role' => 'Admin always holds every Permission, so it can\'t be deleted.']);

    expect(Role::query()->whereKey($adminRole->id)->exists())->toBeTrue()
        ->and(AccessLogEntry::query()->exists())->toBeFalse();
});

it('shows Admin as holding every Permission with Scope everyone, marked so the editor can fix them', function () {
    $admin = employeeWhoManagesRoles('Role Managers');
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
