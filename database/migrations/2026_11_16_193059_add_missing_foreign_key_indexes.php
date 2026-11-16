<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres doesn't index foreign keys on its own. Each Employee's
     * Reports are found by Manager at every level of the reporting line,
     * and a Project's team by Project, so both get an index. Work Entries
     * are also listed by date across everyone, for whoever can lock them,
     * which the existing (assignment_id, date) index can't serve.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('manager_id');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('work_entries', function (Blueprint $table) {
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['manager_id']);
        });
    }
};
