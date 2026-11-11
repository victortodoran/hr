<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create Assignments: an Employee working on a Project for a while, with
     * a Job Title and a share of their time as a whole percentage.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('job_title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('share')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
        });

        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_dates CHECK (end_date IS NULL OR end_date >= start_date)');
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_share CHECK (share IS NULL OR share BETWEEN 1 AND 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
