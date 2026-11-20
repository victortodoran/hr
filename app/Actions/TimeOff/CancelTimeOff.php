<?php

namespace App\Actions\TimeOff;

use App\Enums\TimeOffState;
use App\Models\TimeOff;

/**
 * Cancels an Employee's own Time Off before it starts.
 */
class CancelTimeOff
{
    /**
     * Cancel the Time Off and return whether it changed. It is kept on
     * record and uses no Paid Days from now on. The write only changes Time
     * Off still cancellable, so a decline made since the policy check is
     * never overwritten; then nothing changes and false is returned.
     */
    public function cancel(TimeOff $timeOff): bool
    {
        $changed = TimeOff::query()
            ->whereKey($timeOff->id)
            ->cancellable()
            ->update([
                'state' => TimeOffState::Cancelled,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Time Off is declined or starts between the policy check and this write)
            return false;
        }

        $timeOff->refresh();

        return true;
    }
}
