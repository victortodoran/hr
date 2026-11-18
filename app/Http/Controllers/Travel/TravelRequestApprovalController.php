<?php

namespace App\Http\Controllers\Travel;

use App\Actions\ApproveTravelRequest;
use App\Enums\ApprovalsTab;
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
     * Approve the Travel Request, recording who approved it and when. One
     * decided or cancelled since the policy check is refused, never changed.
     */
    public function store(TravelRequest $travelRequest, #[CurrentUser] Employee $actor, ApproveTravelRequest $approveTravelRequest): RedirectResponse
    {
        $changed = $approveTravelRequest->approve($travelRequest, $actor);
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Request is decided or cancelled between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request approved.')]);

        return to_route('approvals.index', ['tab' => ApprovalsTab::TravelRequests]);
    }
}
