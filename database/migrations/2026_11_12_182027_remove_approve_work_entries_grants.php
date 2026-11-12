<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Approving Work Entries is no longer a Permission: only an Employee's
     * effective Manager approves them. The grants Roles still store for it
     * go.
     */
    public function up(): void
    {
        DB::table('role_permissions')->where('permission', 'approve-work-entries')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * The removed grants are not restored.
     */
    public function down(): void
    {
        //
    }
};
