<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Performance Evaluation is the yearly look at how one Employee did
     * over one calendar year, at most one per Employee per year, and holds
     * the Goals their Manager gave them for it. It is Open until Completed.
     */
    public function up(): void
    {
        Schema::create('performance_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedSmallInteger('year');
            $table->string('state')->default('open');
            $table->timestamps();

            $table->unique(['employee_id', 'year']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->timestamps();

            $table->index('performance_evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
        Schema::dropIfExists('performance_evaluations');
    }
};
