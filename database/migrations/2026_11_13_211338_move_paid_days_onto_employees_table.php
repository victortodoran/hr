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
     * Paid Days become one number on each Employee that applies to every
     * year, instead of one per year. Each Employee keeps the number that
     * applies in the current year: that year's own, or else the most recent
     * earlier year's, or 0. Numbers set only for later years are dropped.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_days')->default(0);
        });

        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_paid_days CHECK (paid_days >= 0)');

        DB::update(
            'UPDATE employees SET paid_days = COALESCE((SELECT days FROM paid_days WHERE paid_days.employee_id = employees.id AND paid_days.year <= ? ORDER BY paid_days.year DESC LIMIT 1), 0)',
            [today()->year],
        );

        Schema::drop('paid_days');
    }

    /**
     * Reverse the migrations.
     *
     * Each Employee's number goes back as their Paid Days for the current
     * year, which later years copy forward.
     */
    public function down(): void
    {
        Schema::create('paid_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('days');
            $table->timestamps();

            $table->unique(['employee_id', 'year']);
        });

        DB::statement('ALTER TABLE paid_days ADD CONSTRAINT paid_days_days CHECK (days >= 0)');

        DB::insert(
            'INSERT INTO paid_days (employee_id, year, days, created_at, updated_at) SELECT id, ?, paid_days, NOW(), NOW() FROM employees',
            [today()->year],
        );

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('paid_days');
        });
    }
};
