<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time Off is one or more whole days an Employee asks to be away, from a
     * first to a last date, Paid or Unpaid. It is Pending, Approved, Declined
     * or Cancelled. Who decided it and when are kept for Approved and
     * Declined Time Off (that of an Employee with no Manager is Approved with no one deciding),
     * a Declined one always has a reason, and a Cancelled one records when it
     * was cancelled.
     */
    public function up(): void
    {
        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->date('first_date');
            $table->date('last_date');
            $table->boolean('is_paid');
            $table->string('state')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'first_date']);
        });

        DB::statement('ALTER TABLE time_off ADD CONSTRAINT time_off_dates CHECK (last_date >= first_date)');
        DB::statement(<<<'SQL'
            ALTER TABLE time_off ADD CONSTRAINT time_off_state CHECK (
                (state = 'pending' AND decided_by IS NULL AND decided_at IS NULL AND decline_reason IS NULL AND cancelled_at IS NULL)
                OR (state = 'approved' AND decided_at IS NOT NULL AND decline_reason IS NULL AND cancelled_at IS NULL)
                OR (state = 'declined' AND decided_by IS NOT NULL AND decided_at IS NOT NULL AND decline_reason IS NOT NULL AND cancelled_at IS NULL)
                OR (state = 'cancelled' AND decline_reason IS NULL AND cancelled_at IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off');
    }
};
