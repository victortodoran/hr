<?php

namespace App\Actions\Travel;

use App\Enums\TravelRequestState;
use App\Models\TravelRequest;

class CancelTravelRequest
{
    /**
     * Only a still-cancellable row changes, so a decline or first date reached
     * since the policy check wins.
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
