<?php

namespace App\Actions\Travel;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

/**
 * Reopens selected Travel Expenses from their Travel Request's page: sends
 * Approved ones back to Pending so the Employee can correct them. Only a
 * Role that grants reopening reopens them; being the Manager is not enough,
 * and nobody reopens their own.
 */
class ReopenTravelExpenses
{
    /**
     * Reopen the selected Travel Expenses of the Travel Request that the
     * actor may reopen: Approved ones of Employees their reopen Scope
     * covers, never their own, sent back to Pending with their approval
     * withdrawn. The rest are skipped. Returns how many were reopened.
     *
     * @param  array<int, int>  $ids
     */
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
