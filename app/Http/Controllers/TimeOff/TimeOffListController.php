<?php

namespace App\Http\Controllers\TimeOff;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffListRequest;
use App\Models\Employee;
use App\Pages\TimeOff\TimeOffListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Time Off page across Employees: the Time Off of the Employees the
 * viewer may see Time Off for, one Period at a time, for every Employee or
 * one. Deciding it stays on Approvals.
 */
class TimeOffListController extends Controller
{
    /**
     * The Time Off matching the filters, with the Employees to choose from
     * (see TimeOffListPage).
     */
    public function index(#[CurrentUser] Employee $actor, TimeOffListRequest $request): Response
    {
        return Inertia::render('time-off/List', TimeOffListPage::props($actor, $request->filters()));
    }
}
