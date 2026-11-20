<?php

namespace App\Actions\Travel;

use App\Enums\TravelExpenseState;
use App\Models\TravelExpense;

class DeleteTravelExpense
{
    use WritesTravelExpenses;

    /**
     * Only a Pending or Declined row is deleted, so an approval made since the
     * policy check wins.
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
