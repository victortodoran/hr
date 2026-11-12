<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

covers(Role::class);

it('removes the approve work entries grants Roles still store, keeping every other grant', function () {
    $manager = Role::factory()->withPermissions([Permission::ViewWorkEntries->value => Scope::Reports])->create();
    DB::table('role_permissions')->insert(['role_id' => $manager->id, 'permission' => 'approve-work-entries', 'scope' => 'reports']);
    $migration = ['--path' => 'database/migrations/2026_11_12_182027_remove_approve_work_entries_grants.php'];

    Artisan::call('migrate:rollback', $migration);
    Artisan::call('migrate', $migration);

    expect(DB::table('role_permissions')->pluck('permission')->all())->toBe(['view-work-entries']);
});
