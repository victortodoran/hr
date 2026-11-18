<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Manager gives each Goal, and the Performance Evaluation as a
     * whole, an Outcome, with a written summary, bit by bit while it is
     * Open. A Completed one always has its overall Outcome, its summary and
     * when it was completed, and every one of its Goals has an Outcome.
     */
    public function up(): void
    {
        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->string('outcome')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('outcome')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE performance_evaluations ADD CONSTRAINT performance_evaluations_state CHECK (
                (state = 'open' AND completed_at IS NULL)
                OR (state = 'completed' AND outcome IS NOT NULL AND summary IS NOT NULL AND completed_at IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE performance_evaluations DROP CONSTRAINT performance_evaluations_state');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });

        Schema::table('performance_evaluations', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'summary', 'completed_at']);
        });
    }
};
