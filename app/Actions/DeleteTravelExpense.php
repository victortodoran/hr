<?php

namespace App\Actions;

use App\Concerns\WritesTravelExpenses;
use App\Enums\TravelExpenseState;
use App\Models\TravelExpense;

/**
 * Deletes a Travel Expense recorded by mistake, with its receipt.
 */
class DeleteTravelExpense
{
    use WritesTravelExpenses;

    /**
     * Delete the Travel Expense and its receipt, and return whether it was
     * deleted. The delete only removes one still Pending or Declined, so an
     * approval made since the policy check is never undone; then nothing is
     * deleted and false is returned.
     */
    public function delete(TravelExpense $travelExpense): bool
    {
        $deleted = TravelExpense::query()
            ->whereKey($travelExpense->id)
            ->whereIn('state', TravelExpenseState::open())
            ->delete();

        if ($deleted === 0) { // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this delete)
            return false;
        }

        $this->discard($travelExpense->receipt_path);

        return true;
    }
}
