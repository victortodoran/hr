<?php

namespace App\Actions;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

/**
 * Declines Pending Time Off, as the Employee's effective Manager.
 */
class DeclineTimeOff
{
    /**
     * Decline the Time Off, recording who declined it, when and why. It is
     * kept on record, uses no Paid Days and the Employee sees the reason.
     */
    public function decline(Employee $actor, TimeOff $timeOff, string $reason): void
    {
        $timeOff->forceFill([
            'state' => TimeOffState::Declined,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decline_reason' => $reason,
        ])->save();
    }
}
