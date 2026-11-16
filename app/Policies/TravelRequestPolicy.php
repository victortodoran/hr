<?php

namespace App\Policies;

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
