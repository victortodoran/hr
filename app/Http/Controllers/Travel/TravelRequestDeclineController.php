<?php

namespace App\Http\Controllers\Travel;

use App\Enums\ApprovalsTab;
use App\Enums\TravelRequestState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestDeclineRequest;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an Employee's effective Manager decline their Pending Travel Request.
 */
class TravelRequestDeclineController extends Controller
{
    /**
     * Decline the Travel Request, recording who declined it, when and why.
     * It is kept on record and the Employee sees the reason.
     */
    public function store(TravelRequestDeclineRequest $request, TravelRequest $travelRequest, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $travelRequest->forceFill([
            'state' => TravelRequestState::Declined,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decline_reason' => $request->reason(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request declined.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TravelRequests]);
    }
}
