<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\CancelTravelRequest;
use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TravelRequestCancellationController extends Controller
{
    public function store(TravelRequest $travelRequest, CancelTravelRequest $cancelTravelRequest): RedirectResponse
    {
        $changed = $cancelTravelRequest->cancel($travelRequest);
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Request stops being cancellable between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request cancelled.')]);

        return to_route('travel-requests.show', $travelRequest);
    }
}
