<?php

namespace App\Actions;

use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

/**
 * Declines a Pending Travel Request, as the Employee's effective Manager.
 */
class DeclineTravelRequest
{
    /**
     * Decline the Travel Request, recording who declined it, when and why.
     * It is kept on record and the Employee sees the reason.
     */
    public function decline(TravelRequest $travelRequest, Employee $actor, string $reason): void
    {
        $travelRequest->forceFill([
            'state' => TravelRequestState::Declined,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decline_reason' => $reason,
        ])->save();
    }
}
