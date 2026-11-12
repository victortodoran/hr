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
 * left, requesting Time Off and, for a Manager, their Direct Reports'
 * Pending Time Off to approve or decline.
 */
class TimeOffController extends Controller
{
    /**
     * Show the Employee's Time Off, latest first, with the weekdays each
     * counts, who decided it and why it was declined, and whether they may
     * still cancel it, and their Paid Days left this year. For an Employee
     * who manages anyone, also the Time Off they are to answer.
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
                    'id' => $timeOff->id,
                    'first_date' => $timeOff->first_date->toDateString(),
                    'last_date' => $timeOff->last_date->toDateString(),
                    'days' => $timeOff->weekdays(),
                    'is_paid' => $timeOff->is_paid,
                    'state' => $timeOff->state->value,
                    'decided_by' => $timeOff->decider?->name,
                    'decline_reason' => $timeOff->decline_reason,
                    'can_cancel' => $actor->can('cancel', $timeOff),
                ])
                ->all(),
            'toApprove' => $actor->managesAnyone() ? $this->toApprove($actor) : null,
        ]);
    }

    /**
     * The Pending Time Off of the Employees whose effective Manager the actor
     * is, earliest first, each with that Employee's Paid Days left in every
     * year it touches. Paid Days left already count the Pending Time Off.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toApprove(Employee $actor): array
    {
        return TimeOff::query()
            ->whereIn('employee_id', Employee::query()->effectivelyManagedBy($actor)->select('employees.id'))
            ->where('state', TimeOffState::Pending)
            ->with('employee:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TimeOff $timeOff): array => [
                'id' => $timeOff->id,
                'employee' => $timeOff->employee->only('id', 'name'),
                'first_date' => $timeOff->first_date->toDateString(),
                'last_date' => $timeOff->last_date->toDateString(),
                'days' => $timeOff->weekdays(),
                'is_paid' => $timeOff->is_paid,
                'paid_days_left' => array_map(fn (int $year): array => [
                    'year' => $year,
                    'days' => $timeOff->employee->paidDaysLeftIn($year),
                ], range($timeOff->first_date->year, $timeOff->last_date->year)),
            ])
            ->all();
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
