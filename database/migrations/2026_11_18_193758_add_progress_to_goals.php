<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every Goal has a Progress, Not Started until the Employee or their
     * Manager changes it, existing Goals included. Every change is kept,
     * append-only, with who made it, when, and the Progress before and
     * after; removing the Goal removes its changes with it.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('progress')->default('not-started');
        });

        Schema::create('progress_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_id')->constrained('employees')->restrictOnDelete();
            $table->string('previous_progress');
            $table->string('progress');
            $table->timestamp('created_at')->useCurrent();

            $table->index('goal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_changes');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }
};
