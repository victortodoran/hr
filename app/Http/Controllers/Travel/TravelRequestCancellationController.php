<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelRequestState;
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
     * other Travel Requests or Time Off on its days.
     */
    public function store(TravelRequest $travelRequest): RedirectResponse
    {
        $travelRequest->forceFill(['state' => TravelRequestState::Cancelled, 'cancelled_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request cancelled.')]);

        return to_route('travel-requests.index');
    }
}
