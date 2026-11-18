<?php

namespace App\Actions;

use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

/**
 * Approves a Pending Travel Request, as the Employee's effective Manager.
 */
class ApproveTravelRequest
{
    /**
     * Approve the Travel Request, recording who approved it and when.
     */
    public function approve(TravelRequest $travelRequest, Employee $actor): void
    {
        $travelRequest->forceFill(['state' => TravelRequestState::Approved, 'decided_by' => $actor->id, 'decided_at' => now()])->save();
    }
}
