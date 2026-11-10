<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Access Log: one append-only row per change to access, kept for
     * investigation in the database. The Role's name is copied in so the
     * row still makes sense once the Role is deleted.
     */
    public function up(): void
    {
        Schema::create('access_log_entries', function (Blueprint $table) {
            $table->id();
            $table->string('change');
            $table->foreignId('actor_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_name');
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_log_entries');
    }
};
