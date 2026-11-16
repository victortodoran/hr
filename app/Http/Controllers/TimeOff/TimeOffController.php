<?php

namespace App\Http\Controllers\TimeOff;

use App\Enums\TimeOffState;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\TimeOffRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Time Off page: the signed-in Employee's own Time Off and Paid Days
 * left, and requesting Time Off. Managers answer their Direct Reports'
 * Time Off on the Approvals page.
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
        return Inertia::render('time-off/Index', [
            'year' => today()->year,
            'today' => today()->toDateString(),
            'paidDaysLeft' => $actor->paidDaysLeftIn(today()->year),
            'timeOff' => $actor->timeOff()
                ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderByDesc('first_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (TimeOff $timeOff): array => [
                    ...$timeOff->details(),
                    'can_cancel' => $actor->can('cancel', $timeOff),
                ])
                ->all(),
        ]);
    }

    /**
     * Request Time Off for the signed-in Employee. It is Pending until their
     * Manager decides, except for an Employee with no Manager, whose Time Off
     * is Approved at once with nobody deciding.
     */
    public function store(TimeOffRequest $request, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $timeOff = $actor->timeOff()->make($request->timeOff());

        if ($actor->hasNoManager()) {
            $timeOff->forceFill(['state' => TimeOffState::Approved, 'decided_at' => now()]);
        }

        $timeOff->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off requested.')]);

        return to_route('time-off.index');
    }
}
