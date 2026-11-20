<?php

namespace App\Actions\Travel;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;

/**
 * Declines selected Travel Expenses from the Approvals page, several at
 * once with one reason. Nobody declines their own Travel Expenses.
 */
class DeclineTravelExpenses
{
    /**
     * Decline the selected Travel Expenses the actor may decline, the same
     * ones they may approve: Pending ones of Employees whose effective
     * Manager they are. Each records who declined it, when and why. The
     * rest are skipped. Returns how many were declined.
     *
     * @param  array<int, int>  $ids
     */
    public function decline(Employee $actor, array $ids, string $reason): int
    {
        return TravelExpense::query()
            ->decidableBy($actor)
            ->whereKey($ids)
            ->update([
                'state' => TravelExpenseState::Declined,
                'declined_by' => $actor->id,
                'declined_at' => now(),
                'decline_reason' => $reason,
            ]);
    }
}
