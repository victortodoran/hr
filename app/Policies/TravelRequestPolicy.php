<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

/**
 * Who may act on Travel Requests. Every Employee makes their own Travel
 * Requests with no Permission needed, and nobody makes one for someone else.
 * Only the Employee's effective Manager approves or declines them: no Role
 * grants that.
 */
class TravelRequestPolicy
{
    /**
     * Seeing a Travel Request on its own page, as Travel Expenses are seen
     * (TravelExpensePolicy::view): the Employee whose Travel Request it is,
     * whoever one of the Permissions for seeing travel
     * (Permission::seeingTravel()) reaches the Employee for, and their
     * effective Manager while they can decide it.
     */
    public function view(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->employee_id === $actor->id
            || $actor->reachesThrough(Permission::seeingTravel(), $travelRequest->employee)
            || $this->decide($actor, $travelRequest);
    }

    /**
     * The Travel Requests page, across Employees, is for someone who sees
     * the travel of anyone besides themselves.
     */
    public function viewList(Employee $actor): bool
    {
        return $actor->seesOthersTravel();
    }

    /**
     * Only the Employee whose Travel Request it is may cancel it, and only
     * while it is Pending or Approved and hasn't started yet.
     */
    public function cancel(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->employee_id === $actor->id && $travelRequest->isCancellable();
    }

    /**
     * Only the Employee's effective Manager may approve or decline their
     * Travel Request, the same rule as Time Off, and only while it is
     * Pending, even once its first date has passed. No Role grants it, not
     * even Admin, and since nobody is their own effective Manager, nobody
     * answers their own.
     */
    public function decide(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->state === TravelRequestState::Pending
            && Employee::query()->effectivelyManagedBy($actor)->whereKey($travelRequest->employee_id)->exists();
    }
}
