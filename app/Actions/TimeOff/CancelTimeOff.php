<?php

namespace App\Actions\TimeOff;

use App\Enums\TimeOffState;
use App\Models\TimeOff;

class CancelTimeOff
{
    /** Only a still-cancellable row changes, so a decline made since the policy check wins. */
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
