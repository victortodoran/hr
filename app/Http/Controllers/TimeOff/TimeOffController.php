<?php

namespace App\Http\Controllers\TimeOff;

use App\Actions\TimeOff\RequestTimeOff;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffRequest;
use App\Models\Employee;
use App\Pages\TimeOff\TimeOffFormPage;
use App\Pages\TimeOff\TimeOffPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TimeOffController extends Controller
{
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('time-off/Index', TimeOffPage::props($actor));
    }

    /** The form opens as a sheet over this page (rule L14), so it has a URL of its own. */
    public function create(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('time-off/Index', TimeOffFormPage::create($actor));
    }

    public function store(TimeOffRequest $request, #[CurrentUser] Employee $actor, RequestTimeOff $requestTimeOff): RedirectResponse
    {
        $requestTimeOff->request($actor, $request->timeOff());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off requested.')]);

        return to_route('time-off.index');
    }
}
