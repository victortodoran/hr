<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\MakeTravelRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestRequest;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Pages\Travel\TravelPage;
use App\Pages\Travel\TravelRequestFormPage;
use App\Pages\Travel\TravelRequestPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Travel page: the signed-in Employee's own Travel Requests, making
 * one in a sheet over it, and the page of each Travel Request.
 */
class TravelRequestController extends Controller
{
    /**
     * Show the Employee's Travel Requests, Upcoming and Past (TravelPage).
     */
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Index', TravelPage::props($actor));
    }

    /**
     * Show the Travel page with the sheet for requesting travel open over
     * it (rule L14), so the form has a URL of its own, with the Assignments
     * the Employee can still travel on (TravelRequestFormPage).
     */
    public function create(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Index', TravelRequestFormPage::create($actor));
    }

    /**
     * Make a Travel Request for the signed-in Employee. It is Pending until
     * their Manager decides, except for an Employee with no Manager, whose
     * Travel Request is Approved at once with nobody deciding.
     */
    public function store(TravelRequestRequest $request, #[CurrentUser] Employee $actor, MakeTravelRequest $makeTravelRequest): RedirectResponse
    {
        $makeTravelRequest->make($actor, $request->travelRequest());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request made.')]);

        return to_route('travel-requests.index');
    }

    /**
     * Show one Travel Request in full with its Travel Expenses, and what the
     * viewer may do to them (TravelRequestPage).
     */
    public function show(TravelRequest $travelRequest, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', TravelRequestPage::props($travelRequest, $actor));
    }
}
