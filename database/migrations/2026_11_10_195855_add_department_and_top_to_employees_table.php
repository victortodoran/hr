<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every Employee belongs to exactly one Department. Exactly one Employee
     * is the top Employee; the partial unique index allows at most one.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->after('email')->constrained()->restrictOnDelete();
            $table->boolean('is_top')->default(false)->after('department_id');
        });

        DB::statement('CREATE UNIQUE INDEX employees_single_top ON employees (is_top) WHERE is_top');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS employees_single_top');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('is_top');
        });
    }
};
