<?php

namespace App\Actions\Travel;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;

class ApproveTravelExpenses
{
    /**
     * Each records who approved it in its own right (ADR 0005).
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
