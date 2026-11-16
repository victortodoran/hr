<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Travel Request is an Employee asking to travel for work on one of
     * their Assignments, with a destination, a first and a last date and a
     * purpose. It is Pending, Approved, Declined or Cancelled. Who decided it
     * and when are kept for Approved and Declined ones (the Travel Request of
     * an Employee with no Manager is Approved with no one deciding), a
     * Declined one always has a reason, and a Cancelled one records when it
     * was cancelled.
     */
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('assignment_id')->constrained();
            $table->string('destination');
            $table->date('first_date');
            $table->date('last_date');
            $table->text('purpose');
            $table->string('state')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'first_date']);
            $table->index('assignment_id');
        });

        DB::statement('ALTER TABLE travel_requests ADD CONSTRAINT travel_requests_dates CHECK (last_date >= first_date)');
        DB::statement(<<<'SQL'
            ALTER TABLE travel_requests ADD CONSTRAINT travel_requests_state CHECK (
                (state = 'pending' AND decided_by IS NULL AND decided_at IS NULL AND decline_reason IS NULL AND cancelled_at IS NULL)
                OR (state = 'approved' AND decided_at IS NOT NULL AND decline_reason IS NULL AND cancelled_at IS NULL)
                OR (state = 'declined' AND decided_by IS NOT NULL AND decided_at IS NOT NULL AND decline_reason IS NOT NULL AND cancelled_at IS NULL)
                OR (state = 'cancelled' AND decline_reason IS NULL AND cancelled_at IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
};
