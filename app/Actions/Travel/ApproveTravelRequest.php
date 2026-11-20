<?php

namespace App\Actions\Travel;

use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

/**
 * Approves a Pending Travel Request, as the Employee's effective Manager.
 */
class ApproveTravelRequest
{
    /**
     * Approve the Travel Request, recording who approved it and when, and
     * return whether it changed. The write only changes one still Pending,
     * so a decision or cancellation made since the policy check is never
     * undone; then nothing changes and false is returned.
     */
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
