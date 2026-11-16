<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\TravelRequest;

/**
 * Who may act on Travel Requests. Every Employee makes their own Travel
 * Requests with no Permission needed, and nobody makes one for someone else.
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
}
