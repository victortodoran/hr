<?php

namespace App\Http\Controllers;

use App\Enums\TimeOffState;
use App\Http\Requests\TimeOffRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Time Off page: the signed-in Employee's own Time Off and Paid Days
 * left, and requesting Time Off.
 */
class TimeOffController extends Controller
{
    /**
     * Show the Employee's Time Off, latest first, with the weekdays each
     * counts and whether they may still cancel it, and their Paid Days left
     * this year.
     */
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('time-off/Index', [
            'year' => today()->year,
            'today' => today()->toDateString(),
            'paidDaysLeft' => $actor->paidDaysLeftIn(today()->year),
            'timeOff' => $actor->timeOff()
                ->orderByDesc('first_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (TimeOff $timeOff): array => [
                    'id' => $timeOff->id,
                    'first_date' => $timeOff->first_date->toDateString(),
                    'last_date' => $timeOff->last_date->toDateString(),
                    'days' => $timeOff->weekdays(),
                    'is_paid' => $timeOff->is_paid,
                    'state' => $timeOff->state->value,
                    'can_cancel' => $actor->can('cancel', $timeOff),
                ])
                ->all(),
        ]);
    }

    /**
     * Request Time Off for the signed-in Employee. It is Pending until their
     * Manager decides, except the top Employee's, which is Approved at once
     * with nobody deciding.
     */
    public function store(TimeOffRequest $request, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $timeOff = $actor->timeOff()->make($request->timeOff());

        if ($actor->is_top) {
            $timeOff->forceFill(['state' => TimeOffState::Approved, 'decided_at' => now()]);
        }

        $timeOff->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time Off requested.')]);

        return to_route('time-off.index');
    }
}
