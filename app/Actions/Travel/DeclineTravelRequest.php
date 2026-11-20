<?php

namespace App\Actions\Travel;

use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

/**
 * Declines a Pending Travel Request, as the Employee's effective Manager.
 */
class DeclineTravelRequest
{
    /**
     * Decline the Travel Request, recording who declined it, when and why,
     * and return whether it changed. It is kept on record and the Employee
     * sees the reason. The write only changes one still Pending, so a
     * decision or cancellation made since the policy check is never undone;
     * then nothing changes and false is returned.
     */
    public function decline(TravelRequest $travelRequest, Employee $actor, string $reason): bool
    {
        $changed = TravelRequest::query()
            ->whereKey($travelRequest->id)
            ->pending()
            ->update([
                'state' => TravelRequestState::Declined,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decline_reason' => $reason,
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Travel Request is decided or cancelled between the policy check and this write)
            return false;
        }

        $travelRequest->refresh();

        return true;
    }
}
