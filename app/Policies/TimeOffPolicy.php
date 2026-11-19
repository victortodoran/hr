<?php

namespace App\Policies;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

/**
 * Who may act on Time Off. Every Employee requests their own Time Off with
 * no Permission needed, and nobody requests it for someone else. Only the
 * Employee's effective Manager approves or declines it: no Role grants that.
 */
class TimeOffPolicy
{
    /**
     * The Time Off page across Employees is for someone who sees the Time
     * Off of anyone besides themselves.
     */
    public function viewList(Employee $actor): bool
    {
        return $actor->seesOthersTimeOff();
    }

    /**
     * Only the Employee whose Time Off it is may cancel it, and only while
     * it is Pending or Approved and hasn't started yet.
     */
    public function cancel(Employee $actor, TimeOff $timeOff): bool
    {
        return $timeOff->employee_id === $actor->id && $timeOff->isCancellable();
    }

    /**
     * Only the Employee's effective Manager may approve or decline their Time
     * Off, the same rule as approving Work Entries, and only while it is
     * Pending. No Role grants it, not even Admin, and since nobody is their
     * own effective Manager, nobody answers their own.
     */
    public function decide(Employee $actor, TimeOff $timeOff): bool
    {
        return $timeOff->state === TimeOffState::Pending
            && Employee::query()->effectivelyManagedBy($actor)->whereKey($timeOff->employee_id)->exists();
    }
}
