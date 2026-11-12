<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\TimeOff;

/**
 * Who may act on Time Off. Every Employee requests their own Time Off with
 * no Permission needed, and nobody requests it for someone else.
 */
class TimeOffPolicy
{
    /**
     * Only the Employee whose Time Off it is may cancel it, and only while
     * it is Pending or Approved and hasn't started yet.
     */
    public function cancel(Employee $actor, TimeOff $timeOff): bool
    {
        return $timeOff->employee_id === $actor->id && $timeOff->isCancellable();
    }
}
