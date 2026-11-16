<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres doesn't index foreign keys on its own. Each Employee's
     * Reports are found by Manager at every level of the reporting line,
     * and a Project's team by Project, so both get an index.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('manager_id');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['manager_id']);
        });
    }
};
