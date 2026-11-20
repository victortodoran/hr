<?php

namespace App\Actions\Travel;

use App\Enums\TravelRequestState;
use App\Models\Employee;

class MakeTravelRequest
{
    /**
     * An Employee with no Manager has nobody to decide, so their Travel Request
     * is Approved at once.
     *
     * @param  array{assignment_id: int, destination: string, first_date: string, last_date: string, purpose: string}  $details
     */
    public function make(Employee $actor, array $details): void
    {
        $travelRequest = $actor->travelRequests()->make($details);

        if ($actor->hasNoManager()) {
            $travelRequest->forceFill(['state' => TravelRequestState::Approved, 'decided_at' => now()]);
        }

        $travelRequest->save();
    }
}
