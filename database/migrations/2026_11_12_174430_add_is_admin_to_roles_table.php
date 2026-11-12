<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Marks the one Admin Role, which always holds every Permission, so its
     * stored grants go. A partial unique index keeps it to a single Role. On
     * an existing install, Admin is the Role named Admin, or else the oldest
     * Role, the starter Admin being created first. Admin is never given to
     * every new Employee.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('name');
        });

        DB::statement('CREATE UNIQUE INDEX roles_one_admin ON roles (is_admin) WHERE is_admin');

        $adminId = DB::table('roles')->where('name', 'Admin')->value('id')
            ?? DB::table('roles')->orderBy('id')->value('id');

        if ($adminId === null) {
            return;
        }

        DB::table('roles')->where('id', $adminId)->update(['is_admin' => true, 'is_given_to_new_employees' => false]);
        DB::table('role_permissions')->where('role_id', $adminId)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX roles_one_admin');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
