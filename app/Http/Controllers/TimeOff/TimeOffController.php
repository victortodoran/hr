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

/**
 * The Time Off page: the signed-in Employee's own Time Off and Paid Days
 * left, and requesting Time Off in a sheet over it. Managers answer
 * their Direct Reports' Time Off on the Approvals page.
 */
class TimeOffController extends Controller
{
    /**
     * Show the Employee's Time Off, latest first, with the weekdays each
     * counts, who decided it and why it was declined, and whether they may
     * still cancel it, and their Paid Days left this year.
     */
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('time-off/Index', TimeOffPage::props($actor));
    }

    /**
     * Show the Time Off page with the sheet for requesting Time Off open
     * over it (rule L14), so the form has a URL of its own, with today, the
     * earliest first date.
     */
    public function create(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('time-off/Index', TimeOffFormPage::create($actor));
    }

    /**
     * Request Time Off for the signed-in Employee. It is Pending until their
     * Manager decides, except for an Employee with no Manager, whose Time Off
     * is Approved at once with nobody deciding.
     */
    public function store(TimeOffRequest $request, #[CurrentUser] Employee $actor, RequestTimeOff $requestTimeOff): RedirectResponse
    {
        $requestTimeOff->request($actor, $request->timeOff());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off requested.')]);

        return to_route('time-off.index');
    }
}
