<?php

namespace App\Actions\Travel;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;

class DeclineTravelExpenses
{
    /** @param  array<int, int>  $ids */
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
