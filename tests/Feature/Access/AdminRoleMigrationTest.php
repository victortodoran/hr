<?php

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

covers(Role::class);

/**
 * Undo the migration, set up Roles as they were before it, and run it again.
 *
 * @param  Closure(): void  $before
 */
function migrateAdminRoleAfter(Closure $before): void
{
    $migration = ['--path' => 'database/migrations/2026_11_12_174430_add_is_admin_to_roles_table.php'];

    Artisan::call('migrate:rollback', $migration);
    $before();
    Artisan::call('migrate', $migration);
}

/**
 * A Role as stored before the migration, with some grants.
 */
function roleBeforeAdmin(string $name): int
{
    $id = DB::table('roles')->insertGetId(['name' => $name, 'is_given_to_new_employees' => false]);
    DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => Permission::ManageRoles->value, 'scope' => null]);

    return $id;
}

it('makes the Role named Admin the Admin Role and drops its stored grants', function () {
    migrateAdminRoleAfter(function () use (&$manager, &$admin) {
        $manager = roleBeforeAdmin('Manager');
        $admin = roleBeforeAdmin('Admin');
    });

    expect(Role::query()->where('is_admin', true)->sole()->id)->toBe($admin)
        ->and(DB::table('role_permissions')->pluck('role_id')->all())->toBe([$manager]);
});

it('makes the oldest Role the Admin Role when none is named Admin', function () {
    migrateAdminRoleAfter(function () use (&$owners) {
        $owners = roleBeforeAdmin('Owners');
        roleBeforeAdmin('Manager');
    });

    expect(Role::query()->where('is_admin', true)->sole()->id)->toBe($owners);
});

it('makes no Role the Admin Role when there are none', function () {
    migrateAdminRoleAfter(fn () => null);

    expect(Role::query()->where('is_admin', true)->exists())->toBeFalse();
});
