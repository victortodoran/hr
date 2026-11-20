<?php

namespace App\Http\Controllers\TimeOff;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffListRequest;
use App\Models\Employee;
use App\Pages\TimeOff\TimeOffListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class TimeOffListController extends Controller
{
    public function index(#[CurrentUser] Employee $actor, TimeOffListRequest $request): Response
    {
        return Inertia::render('time-off/List', TimeOffListPage::props($actor, $request->filters()));
    }
}
