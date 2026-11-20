<?php

namespace App\Actions\TimeOff;

use App\Enums\TimeOffState;
use App\Models\Employee;
use App\Models\TimeOff;

class ApproveTimeOff
{
    /** Only a still-Pending row changes, so a decision made since the policy check wins. */
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
