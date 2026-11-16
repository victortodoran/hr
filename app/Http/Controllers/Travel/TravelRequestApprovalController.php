<?php

namespace App\Http\Controllers\Travel;

use App\Enums\ApprovalsTab;
use App\Enums\TravelRequestState;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an Employee's effective Manager approve their Pending Travel Request.
 */
class TravelRequestApprovalController extends Controller
{
    /**
     * Approve the Travel Request, recording who approved it and when.
     */
    public function store(TravelRequest $travelRequest, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $travelRequest->forceFill(['state' => TravelRequestState::Approved, 'decided_by' => $actor->id, 'decided_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request approved.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TravelRequests]);
    }
}
