<?php

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestListRequest;
use App\Models\Employee;
use App\Pages\Travel\TravelRequestListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class TravelRequestListController extends Controller
{
    public function index(#[CurrentUser] Employee $actor, TravelRequestListRequest $request): Response
    {
        return Inertia::render('travel-requests/List', TravelRequestListPage::props($actor, $request->filters()));
    }
}
