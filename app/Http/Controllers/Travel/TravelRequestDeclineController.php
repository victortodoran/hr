<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\DeclineTravelRequest;
use App\Enums\ApprovalsTab;
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
     * It is kept on record and the Employee sees the reason. One decided or
     * cancelled since the policy check is refused, never changed. It is
     * decided from the Approvals page or its own page, and the actor goes
     * back to where they decided it, unless they may no longer see it once
     * decided, then to the Approvals page's Travel Requests tab.
     */
    public function store(TravelRequestDeclineRequest $request, TravelRequest $travelRequest, #[CurrentUser] Employee $actor, DeclineTravelRequest $declineTravelRequest): RedirectResponse
    {
        $changed = $declineTravelRequest->decline($travelRequest, $actor, $request->reason());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Request is decided or cancelled between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request declined.')]);

        return $actor->can('view', $travelRequest)
            ? back()
            : to_route('approvals.index', ['tab' => ApprovalsTab::TravelRequests]);
    }
}
