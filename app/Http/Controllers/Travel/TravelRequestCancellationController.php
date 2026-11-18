<?php

namespace App\Http\Controllers\Travel;

use App\Actions\CancelTravelRequest;
use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an Employee cancel their own Travel Request before it starts.
 */
class TravelRequestCancellationController extends Controller
{
    /**
     * Cancel the Travel Request. It is kept on record and no longer blocks
     * other Travel Requests or Time Off on its days. One no longer
     * cancellable since the policy check is refused, never changed.
     */
    public function store(TravelRequest $travelRequest, CancelTravelRequest $cancelTravelRequest): RedirectResponse
    {
        $changed = $cancelTravelRequest->cancel($travelRequest);
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Request stops being cancellable between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request cancelled.')]);

        return to_route('travel-requests.show', $travelRequest);
    }
}
