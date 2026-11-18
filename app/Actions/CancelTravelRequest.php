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
     * Cancel the Travel Request and return whether it changed. It is kept on
     * record and no longer blocks other Travel Requests or Time Off on its
     * days. The write only changes one still cancellable, so a decline,
     * cancellation or first date reached since the policy check is never
     * overridden; then nothing changes and false is returned.
     */
    public function cancel(TravelRequest $travelRequest): bool
    {
        $changed = TravelRequest::query()
            ->whereKey($travelRequest->id)
            ->cancellable()
            ->update([
                'state' => TravelRequestState::Cancelled,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Travel Request stops being cancellable between the policy check and this write)
            return false;
        }

        $travelRequest->refresh();

        return true;
    }
}
