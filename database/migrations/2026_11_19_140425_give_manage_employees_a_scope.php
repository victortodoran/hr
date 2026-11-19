<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manage employees now carries a Scope, and adding Employees is its own
     * company-wide Permission. Every Role holding manage employees keeps
     * its reach: the everyone Scope, and add employees beside it.
     */
    public function up(): void
    {
        DB::table('role_permissions')->where('permission', 'manage-employees')->update(['scope' => 'everyone']);

        DB::statement(<<<'SQL'
            insert into role_permissions (role_id, permission, scope, created_at, updated_at)
            select role_id, 'add-employees', null, now(), now()
            from role_permissions
            where permission = 'manage-employees'
            on conflict (role_id, permission) do nothing
            SQL);
    }

    /**
     * Reverse the migrations.
     *
     * Manage employees goes back to company-wide and add employees goes,
     * so Roles holding only add employees lose it.
     */
    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'add-employees')->delete();

        DB::table('role_permissions')->where('permission', 'manage-employees')->update(['scope' => null]);
    }
};
