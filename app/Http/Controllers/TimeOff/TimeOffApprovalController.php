<?php

namespace App\Http\Controllers\TimeOff;

use App\Actions\ApproveTimeOff;
use App\Enums\ApprovalsTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffApprovalRequest;
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
    public function store(TimeOffApprovalRequest $request, TimeOff $timeOff, #[CurrentUser] Employee $actor, ApproveTimeOff $approveTimeOff): RedirectResponse
    {
        $approveTimeOff->approve($actor, $timeOff);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off approved.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TimeOff]);
    }
}
