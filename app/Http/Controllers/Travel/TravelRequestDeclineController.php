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

class TravelRequestDeclineController extends Controller
{
    /** Deciding can take it out of what the actor may see, so they then go to the Approvals page instead. */
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
