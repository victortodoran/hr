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
     * Decline the Time Off, recording who declined it, when and why, and
     * return whether it changed. It is kept on record, uses no Paid Days and
     * the Employee sees the reason. The write only changes Time Off still
     * Pending, so a cancellation or decision made since the policy check is
     * never overwritten; then nothing changes and false is returned.
     */
    public function decline(Employee $actor, TimeOff $timeOff, string $reason): bool
    {
        $changed = TimeOff::query()
            ->whereKey($timeOff->id)
            ->pending()
            ->update([
                'state' => TimeOffState::Declined,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decline_reason' => $reason,
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Time Off is cancelled or decided between the policy check and this write)
            return false;
        }

        $timeOff->refresh();

        return true;
    }
}
