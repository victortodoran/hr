<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * There is no longer a single top Employee: any number of Employees can
     * have no Manager, which their empty manager_id already says.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX employees_single_top');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_top');
        });
    }

    /**
     * Bring the flag back, marking no one: which Employee was on top isn't
     * known any more.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_top')->default(false)->after('department_id');
        });

        DB::statement('CREATE UNIQUE INDEX employees_single_top ON employees (is_top) WHERE is_top');
    }
};
