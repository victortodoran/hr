<?php

namespace App\Http\Controllers\TimeOff;

use App\Actions\TimeOff\ApproveTimeOff;
use App\Enums\ApprovalsTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffApprovalRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TimeOffApprovalController extends Controller
{
    public function store(TimeOffApprovalRequest $request, TimeOff $timeOff, #[CurrentUser] Employee $actor, ApproveTimeOff $approveTimeOff): RedirectResponse
    {
        $changed = $approveTimeOff->approve($actor, $timeOff);
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Time Off is cancelled or decided between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off approved.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TimeOff]);
    }
}
