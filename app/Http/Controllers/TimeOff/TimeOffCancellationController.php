<?php

namespace App\Http\Controllers\TimeOff;

use App\Actions\CancelTimeOff;
use App\Http\Controllers\Controller;
use App\Models\TimeOff;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an Employee cancel their own Time Off before it starts.
 */
class TimeOffCancellationController extends Controller
{
    /**
     * Cancel the Time Off. It is kept on record and uses no Paid Days from
     * now on.
     */
    public function store(TimeOff $timeOff, CancelTimeOff $cancelTimeOff): RedirectResponse
    {
        $changed = $cancelTimeOff->cancel($timeOff);
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Time Off is declined or starts between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off cancelled.')]);

        return to_route('time-off.index');
    }
}
