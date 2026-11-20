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

class TravelRequestController extends Controller
{
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Index', TravelPage::props($actor));
    }

    /** The form opens as a sheet over this page (rule L14), so it has a URL of its own. */
    public function create(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Index', TravelRequestFormPage::create($actor));
    }

    public function store(TravelRequestRequest $request, #[CurrentUser] Employee $actor, MakeTravelRequest $makeTravelRequest): RedirectResponse
    {
        $makeTravelRequest->make($actor, $request->travelRequest());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request made.')]);

        return to_route('travel-requests.index');
    }

    public function show(TravelRequest $travelRequest, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', TravelRequestPage::props($travelRequest, $actor));
    }
}
