<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Work Entry is how long an Employee worked on one Assignment on one
     * date, with what they did. Several can share an Assignment and a date.
     * The time is kept as whole minutes in quarter-hour steps, so totals add
     * up exactly.
     */
    public function up(): void
    {
        Schema::create('work_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained();
            $table->date('date');
            $table->unsignedSmallInteger('minutes');
            $table->text('description');
            $table->timestamps();

            $table->index(['assignment_id', 'date']);
        });

        DB::statement('ALTER TABLE work_entries ADD CONSTRAINT work_entries_minutes CHECK (minutes > 0 AND minutes <= 1440 AND minutes % 15 = 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_entries');
    }
};
