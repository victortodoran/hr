<?php

namespace App\Actions;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

/**
 * Approves Pending Time Off, as the Employee's effective Manager.
 */
class ApproveTimeOff
{
    /**
     * Approve the Time Off, recording who approved it and when.
     */
    public function approve(Employee $actor, TimeOff $timeOff): void
    {
        $timeOff->forceFill(['state' => TimeOffState::Approved, 'decided_by' => $actor->id, 'decided_at' => now()])->save();
    }
}
