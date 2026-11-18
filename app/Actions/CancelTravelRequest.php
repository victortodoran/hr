<?php

namespace App\Actions;

use App\Enums\TravelRequestState;
use App\Models\TravelRequest;

/**
 * Cancels an Employee's own Travel Request before it starts.
 */
class CancelTravelRequest
{
    /**
     * Cancel the Travel Request. It is kept on record and no longer blocks
     * other Travel Requests or Time Off on its days.
     */
    public function cancel(TravelRequest $travelRequest): void
    {
        $travelRequest->forceFill(['state' => TravelRequestState::Cancelled, 'cancelled_at' => now()])->save();
    }
}
