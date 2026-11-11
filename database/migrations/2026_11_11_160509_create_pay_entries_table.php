<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An Assignment's Pay is a dated history (ADR 0002): each entry takes
     * effect from a date, at most one per day, and is Fixed Pay per month or
     * Hourly Pay, in minor units of the company currency.
     */
    public function up(): void
    {
        Schema::create('pay_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained();
            $table->date('effective_from');
            $table->string('kind');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['assignment_id', 'effective_from']);
        });

        DB::statement("ALTER TABLE pay_entries ADD CONSTRAINT pay_entries_kind CHECK (kind IN ('fixed', 'hourly'))");
        DB::statement('ALTER TABLE pay_entries ADD CONSTRAINT pay_entries_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_entries');
    }
};
