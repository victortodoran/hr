<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An Employee's Paid Days, one number per calendar year. A year with no
     * row of its own uses the most recent earlier year's number.
     */
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_days');
    }
};
