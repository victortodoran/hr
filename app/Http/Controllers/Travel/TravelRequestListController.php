<?php

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestListRequest;
use App\Models\Employee;
use App\Pages\Travel\TravelRequestListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Travel Requests page: the Travel Requests of the Employees the viewer
 * may see travel of, one Period at a time, for every Employee or one, each
 * with a summary of its Travel Expenses. Travel Expenses are only ever
 * shown as part of their Travel Request, so they are reopened on its page.
 */
class TravelRequestListController extends Controller
{
    /**
     * The Travel Requests matching the filters, with the Employees and
     * Projects to choose from (see TravelRequestListPage).
     */
    public function index(#[CurrentUser] Employee $actor, TravelRequestListRequest $request): Response
    {
        return Inertia::render('travel-requests/List', TravelRequestListPage::props($actor, $request->filters()));
    }
}
