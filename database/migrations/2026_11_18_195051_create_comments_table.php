<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments are the running conversation on a Goal between the Employee
     * and their Manager: plain text with its author and when it was
     * written, never changed or removed on its own. Removing the Goal
     * removes its Comments with it.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('employees')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index('goal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
