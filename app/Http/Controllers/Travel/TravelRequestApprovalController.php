<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\ApproveTravelRequest;
use App\Enums\ApprovalsTab;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TravelRequestApprovalController extends Controller
{
    /** Deciding can take it out of what the actor may see, so they then go to the Approvals page instead. */
    public function store(TravelRequest $travelRequest, #[CurrentUser] Employee $actor, ApproveTravelRequest $approveTravelRequest): RedirectResponse
    {
        $changed = $approveTravelRequest->approve($travelRequest, $actor);
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Request is decided or cancelled between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request approved.')]);

        return $actor->can('view', $travelRequest)
            ? back()
            : to_route('approvals.index', ['tab' => ApprovalsTab::TravelRequests]);
    }
}
