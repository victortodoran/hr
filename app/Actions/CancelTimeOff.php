<?php

namespace App\Actions;

use App\Enums\TimeOffState;
use App\Models\TimeOff;

/**
 * Cancels an Employee's own Time Off before it starts.
 */
class CancelTimeOff
{
    /**
     * Cancel the Time Off. It is kept on record and uses no Paid Days from
     * now on.
     */
    public function cancel(TimeOff $timeOff): void
    {
        $timeOff->forceFill(['state' => TimeOffState::Cancelled, 'cancelled_at' => now()])->save();
    }
}
