<?php

namespace App\Actions\Travel;

use App\Enums\TravelRequestState;
use App\Models\Employee;

/**
 * Makes a Travel Request for the signed-in Employee.
 */
class MakeTravelRequest
{
    /**
     * Make the Travel Request. It is Pending until the Employee's Manager
     * decides, except for an Employee with no Manager, whose Travel Request
     * is Approved at once with nobody deciding.
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
