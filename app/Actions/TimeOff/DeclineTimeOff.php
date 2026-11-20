<?php

namespace App\Actions\TimeOff;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

class DeclineTimeOff
{
    /** Only a still-Pending row changes, so a decision made since the policy check wins. */
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
