<?php

namespace App\Policies;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

class TimeOffPolicy
{
    public function viewList(Employee $actor): bool
    {
        return $actor->seesOthersTimeOff();
    }

    public function cancel(Employee $actor, TimeOff $timeOff): bool
    {
        return $timeOff->employee_id === $actor->id && $timeOff->isCancellable();
    }

    /**
     * No Role grants it, not even Admin, and since nobody is their own effective
     * Manager, nobody answers their own.
     */
    public function decide(Employee $actor, TimeOff $timeOff): bool
    {
        return $timeOff->state === TimeOffState::Pending
            && Employee::query()->effectivelyManagedBy($actor)->whereKey($timeOff->employee_id)->exists();
    }
}
