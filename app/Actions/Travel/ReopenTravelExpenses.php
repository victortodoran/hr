<?php

namespace App\Actions\Travel;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

class ReopenTravelExpenses
{
    /** @param  array<int, int>  $ids */
    public function reopen(Employee $actor, TravelRequest $travelRequest, array $ids): int
    {
        return TravelExpense::query()
            ->ofEmployees(Employee::query()->reopenableBy($actor, Permission::ReopenTravelExpenses))
            ->where('travel_request_id', $travelRequest->id)
            ->whereKey($ids)
            ->approved()
            ->update(['state' => TravelExpenseState::Pending, 'approved_by' => null, 'approved_at' => null]);
    }
}
