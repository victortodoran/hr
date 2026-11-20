<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

/** Reopening comes from a Role, like that of Work Entries (ADR 0005). */
class TravelExpensePolicy
{
    public function view(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $travelExpense->travelRequest->employee_id === $actor->id
            || $actor->reachesThrough(Permission::seeingTravel(), $travelExpense->travelRequest->employee)
            || $this->decide($actor, $travelExpense);
    }

    public function reopen(Employee $actor): bool
    {
        return $actor->holds(Permission::ReopenTravelExpenses);
    }

    /** Allowed even after the Employee has left; no Role grants it, not even Admin. */
    public function decide(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $travelExpense->state === TravelExpenseState::Pending
            && Employee::query()->effectivelyManagedBy($actor)->whereKey($travelExpense->travelRequest->employee_id)->exists();
    }

    /** A Travel Request Approved before it was Cancelled still takes Travel Expenses (ADR 0007). */
    public function create(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->employee_id === $actor->id && $travelRequest->takesExpenses();
    }

    public function update(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $travelExpense->isOpen() && $travelExpense->travelRequest->employee_id === $actor->id;
    }

    public function delete(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $this->update($actor, $travelExpense);
    }
}
