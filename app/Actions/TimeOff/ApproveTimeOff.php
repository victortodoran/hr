<?php

namespace App\Actions\TimeOff;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

/**
 * Approves Pending Time Off, as the Employee's effective Manager.
 */
class ApproveTimeOff
{
    /**
     * Approve the Time Off, recording who approved it and when, and return
     * whether it changed. The write only changes Time Off still Pending, so
     * a cancellation or decision made since the policy check is never
     * overwritten; then nothing changes and false is returned.
     */
    public function approve(Employee $actor, TimeOff $timeOff): bool
    {
        $changed = TimeOff::query()
            ->whereKey($timeOff->id)
            ->pending()
            ->update([
                'state' => TimeOffState::Approved,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Time Off is cancelled or decided between the policy check and this write)
            return false;
        }

        $timeOff->refresh();

        return true;
    }
}
