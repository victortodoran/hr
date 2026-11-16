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
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_top');
        });
    }

    /**
     * Bring the flag back, marking as top the longest-serving Employee with
     * no Manager, since the code before this needs exactly one.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_top')->default(false)->after('department_id');
        });

        DB::statement(<<<'SQL'
            UPDATE employees SET is_top = true WHERE id = (
                SELECT id FROM employees WHERE manager_id IS NULL ORDER BY start_date, id LIMIT 1
            )
            SQL);
        DB::statement('CREATE UNIQUE INDEX employees_single_top ON employees (is_top) WHERE is_top');
    }
};
