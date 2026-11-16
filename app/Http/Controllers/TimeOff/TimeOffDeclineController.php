<?php

namespace App\Http\Controllers\TimeOff;

use App\Enums\ApprovalsTab;
use App\Enums\TimeOffState;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffDeclineRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an Employee's effective Manager decline their Pending Time Off.
 */
class TimeOffDeclineController extends Controller
{
    /**
     * Decline the Time Off, recording who declined it, when and why. It is
     * kept on record, uses no Paid Days and the Employee sees the reason.
     */
    public function store(TimeOffDeclineRequest $request, TimeOff $timeOff, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $timeOff->forceFill([
            'state' => TimeOffState::Declined,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decline_reason' => $request->reason(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off declined.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TimeOff]);
    }
}
