<?php

namespace App\Http\Controllers;

use App\Enums\TimeOffState;
use App\Http\Requests\TimeOffApprovalRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an Employee's effective Manager approve their Pending Time Off.
 */
class TimeOffApprovalController extends Controller
{
    /**
     * Approve the Time Off, recording who approved it and when.
     */
    public function store(TimeOffApprovalRequest $request, TimeOff $timeOff, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $timeOff->forceFill(['state' => TimeOffState::Approved, 'decided_by' => $actor->id, 'decided_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off approved.')]);

        return to_route('time-off.index');
    }
}
