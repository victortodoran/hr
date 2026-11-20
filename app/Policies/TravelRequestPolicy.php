<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelRequest;

class TravelRequestPolicy
{
    public function view(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->employee_id === $actor->id
            || $actor->reachesThrough(Permission::seeingTravel(), $travelRequest->employee)
            || $this->decide($actor, $travelRequest);
    }

    public function viewList(Employee $actor): bool
    {
        return $actor->seesOthersTravel();
    }

    public function cancel(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->employee_id === $actor->id && $travelRequest->isCancellable();
    }

    /** Allowed even once its first date has passed; no Role grants it, not even Admin. */
    public function decide(Employee $actor, TravelRequest $travelRequest): bool
    {
        return $travelRequest->state === TravelRequestState::Pending
            && Employee::query()->effectivelyManagedBy($actor)->whereKey($travelRequest->employee_id)->exists();
    }
}
