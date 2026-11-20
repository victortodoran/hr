<?php

namespace App\Http\Controllers\TimeOff;

use App\Actions\TimeOff\DeclineTimeOff;
use App\Enums\ApprovalsTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffDeclineRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TimeOffDeclineController extends Controller
{
    public function store(TimeOffDeclineRequest $request, TimeOff $timeOff, #[CurrentUser] Employee $actor, DeclineTimeOff $declineTimeOff): RedirectResponse
    {
        $changed = $declineTimeOff->decline($actor, $timeOff, $request->reason());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Time Off is cancelled or decided between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off declined.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TimeOff]);
    }
}
