<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Travel Expense is money an Employee paid themselves for the travel
     * of one of their Travel Requests: a positive amount in minor units of
     * the company currency, the date it was paid, a Travel Expense
     * Category, a description and the path of its receipt on the private
     * disk. Like a Work Entry it is Pending, Approved, Declined or Locked.
     * Approved and Locked ones keep when they were approved (the Travel
     * Expense of an Employee with no Manager is Approved with no one
     * approving), Locked ones also who locked them and when, and Declined
     * ones who declined them, when and why; in every other state those stay
     * empty.
     */
    public function up(): void
    {
        Schema::create('travel_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount');
            $table->string('category');
            $table->text('description');
            $table->string('receipt_path');
            $table->string('state')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('declined_by')->nullable()->constrained('employees')->restrictOnDelete();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();

            $table->index(['travel_request_id', 'paid_on']);
        });

        DB::statement('ALTER TABLE travel_expenses ADD CONSTRAINT travel_expenses_amount CHECK (amount > 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE travel_expenses ADD CONSTRAINT travel_expenses_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'approved' AND approved_at IS NOT NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'locked' AND approved_at IS NOT NULL AND locked_by IS NOT NULL AND locked_at IS NOT NULL)
                OR (state = 'declined' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE travel_expenses ADD CONSTRAINT travel_expenses_decline CHECK (
                (state = 'declined' AND declined_by IS NOT NULL AND declined_at IS NOT NULL AND decline_reason IS NOT NULL)
                OR (state <> 'declined' AND declined_by IS NULL AND declined_at IS NULL AND decline_reason IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_expenses');
    }
};
