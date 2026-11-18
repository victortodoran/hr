<?php

namespace App\Actions;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;

/**
 * Approves selected Travel Expenses from the Approvals page, several at
 * once. Nobody approves their own Travel Expenses.
 */
class ApproveTravelExpenses
{
    /**
     * Approve the selected Travel Expenses the actor may approve: Pending
     * ones of Employees whose effective Manager they are. Each records who
     * approved it and when, in its own right (ADR 0005). The rest are
     * skipped. Returns how many were approved.
     *
     * @param  array<int, int>  $ids
     */
    public function approve(Employee $actor, array $ids): int
    {
        return TravelExpense::query()
            ->decidableBy($actor)
            ->whereKey($ids)
            ->update(['state' => TravelExpenseState::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);
    }
}
