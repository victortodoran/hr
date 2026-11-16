<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

/**
 * Who may act on Travel Expenses. Every Employee records their own, on
 * their own Travel Requests, with no Permission needed, and nobody records
 * one for someone else. Only the Employee's effective Manager approves or
 * declines them: no Role grants that. Reopening and locking them come from
 * a Role, like those of Work Entries (ADR 0005).
 */
class TravelExpensePolicy
{
    /**
     * Seeing a Travel Expense and its receipt: the Employee whose Travel
     * Expense it is, their effective Manager while they can answer it, and
     * whoever one of the Permissions for seeing them
     * (Permission::seeingTravelExpenses()) reaches the Employee for.
     */
    public function view(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $travelExpense->travelRequest->employee_id === $actor->id
            || $actor->reachesThrough(Permission::seeingTravelExpenses(), $travelExpense->travelRequest->employee)
            || $this->decide($actor, $travelExpense);
    }

    /**
     * The Travel Expenses page, across Employees, is for someone who sees
     * the Travel Expenses of anyone besides themselves.
     */
    public function viewList(Employee $actor): bool
    {
        return $actor->seesOthersTravelExpenses();
    }

    /**
     * Reopening Travel Expenses needs reopen travel expenses from a Role;
     * whose Travel Expenses it covers depends on its Scope, never the
     * reopener's own.
     */
    public function reopen(Employee $actor): bool
    {
        return $actor->holds(Permission::ReopenTravelExpenses);
    }

    /**
     * Locking Travel Expenses needs lock travel expenses, which applies
     * company-wide, the locker's own Travel Expenses included.
     */
    public function lock(Employee $actor): bool
    {
        return $actor->holds(Permission::LockTravelExpenses);
    }

    /**
     * Only the Employee's effective Manager may approve or decline their
     * Travel Expense, the same rule as Work Entries, and only while it is
     * Pending, even after the Employee has left. No Role grants it, not
     * even Admin, and since nobody is their own effective Manager, nobody
     * answers their own.
     */
    public function decide(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $travelExpense->state === TravelExpenseState::Pending
            && Employee::query()->effectivelyManagedBy($actor)->whereKey($travelExpense->travelRequest->employee_id)->exists();
    }

    /**
     * Only the Employee whose Travel Request it is may record a Travel
     * Expense on it, and only once it is Approved, or was Approved before
     * it was Cancelled (ADR 0007).
     */
    public function create(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->employee_id === $actor->id && $travelRequest->takesExpenses();
    }

    /**
     * Only the Employee whose Travel Expense it is may change it, and only
     * while it is Pending or Declined: an Approved or Locked one can't be.
     */
    public function update(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $travelExpense->isOpen() && $travelExpense->travelRequest->employee_id === $actor->id;
    }

    /**
     * Deleting a Travel Expense follows the same rule as changing it.
     */
    public function delete(Employee $actor, TravelExpense $travelExpense): bool
    {
        return $this->update($actor, $travelExpense);
    }
}
