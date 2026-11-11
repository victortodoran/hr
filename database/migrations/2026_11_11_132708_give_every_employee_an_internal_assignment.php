<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every Employee has an Assignment to the Internal Project for their whole
 * period of work (ADR 0002). Its Job Title is the one the directory shows,
 * which may not be known yet, so a Job Title becomes optional (only the Internal
 * Assignment may leave it empty). Existing Employees get theirs here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('job_title')->nullable()->change();
        });

        $internalProjectId = DB::table('projects')->where('is_internal', true)->value('id');

        if ($internalProjectId !== null) {
            DB::statement(<<<'SQL'
                INSERT INTO assignments (employee_id, project_id, start_date, end_date, created_at, updated_at)
                SELECT employees.id, ?, employees.start_date, employees.leaving_date, now(), now()
                FROM employees
                WHERE NOT EXISTS (
                    SELECT 1 FROM assignments WHERE assignments.employee_id = employees.id AND assignments.project_id = ?
                )
                SQL, [$internalProjectId, $internalProjectId]);
        }
    }

    public function down(): void
    {
        DB::table('assignments')->whereIn('project_id', DB::table('projects')->where('is_internal', true)->select('id'))->delete();

        Schema::table('assignments', function (Blueprint $table) {
            $table->string('job_title')->nullable(false)->change();
        });
    }
};
