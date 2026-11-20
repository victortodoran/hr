<?php

namespace App\Actions\Travel;

use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

class ApproveTravelRequest
{
    /** Only a still-Pending row changes, so a decision made since the policy check wins. */
    public function approve(TravelRequest $travelRequest, Employee $actor): bool
    {
        $changed = TravelRequest::query()
            ->whereKey($travelRequest->id)
            ->pending()
            ->update([
                'state' => TravelRequestState::Approved,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Travel Request is decided or cancelled between the policy check and this write)
            return false;
        }

        $travelRequest->refresh();

        return true;
    }
}
