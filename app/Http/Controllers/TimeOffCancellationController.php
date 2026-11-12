<?php

namespace App\Http\Controllers;

use App\Enums\TimeOffState;
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
    public function store(TimeOff $timeOff): RedirectResponse
    {
        $timeOff->forceFill(['state' => TimeOffState::Cancelled, 'cancelled_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off cancelled.')]);

        return to_route('time-off.index');
    }
}
