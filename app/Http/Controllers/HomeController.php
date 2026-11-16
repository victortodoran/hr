<?php

namespace App\Http\Controllers;

use App\Enums\TimeOffState;
use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home, where every Employee lands after signing in: a summary of their
 * week and year of hours, their Time Off and what waits for them to decide. Each block is
 * null when it doesn't apply to them. Nothing is logged or decided here.
 */
class HomeController extends Controller
{
    /**
     * Show the signed-in Employee's summary.
     */
    public function __invoke(#[CurrentUser] Employee $employee): Response
    {
        $waiting = $employee->waitingForDecision();

        return Inertia::render('Home', [
            'hoursThisWeek' => $this->hoursThisWeek($employee),
            'hoursThisYear' => $this->hoursThisYear($employee),
            'paidDaysLeft' => [
                'year' => today()->year,
                'days' => $employee->paidDaysLeftIn(today()->year),
            ],
            'nextTimeOff' => $employee->timeOff()
                ->standing()
                ->whereDate('last_date', '>=', today())
                ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('first_date')
                ->first()
                ?->details(),
            'declinedAhead' => $this->declinedAhead($employee),
            'waitingForYou' => array_sum($waiting) > 0 ? $waiting : null,
        ]);
    }

    /**
     * The Employee's Declined Time Off still to come, soonest first, so they
     * see why; null when there is none.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function declinedAhead(Employee $employee): ?array
    {
        $declined = $employee->timeOff()
            ->where('state', TimeOffState::Declined)
            ->whereDate('first_date', '>', today())
            ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->get();

        return $declined->isEmpty() ? null : $declined->map(fn (TimeOff $timeOff): array => $timeOff->details())->all();
    }

    /**
     * The Employee's hours this week, Monday to Sunday, leaving out Declined
     * Work Entries, and how many of its Work Entries are Pending; null for
     * the top Employee, who records no Work Entries, and for anyone who
     * can't see their Work Entries.
     *
     * @return array{week: string, hours: float|int, pending: int}|null
     */
    private function hoursThisWeek(Employee $employee): ?array
    {
        if ($employee->is_top || ! $employee->canSeeWorkEntries()) {
            return null;
        }

        $monday = CarbonImmutable::today()->startOfWeek(CarbonInterface::MONDAY);
        $entries = $employee->workEntries()
            ->counted()
            ->whereBetween('work_entries.date', [$monday->toDateString(), $monday->endOfWeek(CarbonInterface::SUNDAY)->toDateString()])
            ->get();

        return [
            'week' => $monday->toDateString(),
            'hours' => $entries->sum(fn (WorkEntry $workEntry): float|int => $workEntry->hours),
            'pending' => $entries->where('state', WorkEntryState::Pending)->count(),
        ];
    }

    /**
     * The Employee's hours for each month of this year, leaving out Declined
     * Work Entries, with how many of them are Pending, and the year's totals.
     * The months run from January, or from their Start Date's month when
     * they started this year, to this month, those with no hours at 0. Null
     * when hours this week is, for the same Employees.
     *
     * @return array{year: int, hours: float|int, pending: float|int, months: array<int, array{month: string, hours: float|int, pending: float|int}>}|null
     */
    private function hoursThisYear(Employee $employee): ?array
    {
        if ($employee->is_top || ! $employee->canSeeWorkEntries()) {
            return null;
        }

        $today = CarbonImmutable::today();
        $january = $today->startOfYear();
        $first = $employee->start_date->isAfter($january) ? $employee->start_date->toImmutable()->startOfMonth() : $january;
        $minutes = $employee->workEntries()
            ->counted()
            ->whereBetween('work_entries.date', [$january->toDateString(), $today->toDateString()])
            ->selectRaw("to_char(work_entries.date, 'YYYY-MM') as month")
            ->selectRaw('sum(work_entries.minutes) as minutes')
            ->selectRaw('sum(case when work_entries.state = ? then work_entries.minutes else 0 end) as pending', [WorkEntryState::Pending->value])
            ->groupBy('month')
            ->toBase()
            ->get()
            ->keyBy('month');
        $months = array_map(fn (CarbonInterface $month): array => [
            'month' => $month->format('Y-m'),
            'hours' => (int) ($minutes[$month->format('Y-m')]->minutes ?? 0) / 60,
            'pending' => (int) ($minutes[$month->format('Y-m')]->pending ?? 0) / 60,
        ], CarbonPeriod::create($first, '1 month', $today->startOfMonth())->toArray());

        return [
            'year' => $today->year,
            'hours' => (int) $minutes->sum('minutes') / 60,
            'pending' => (int) $minutes->sum('pending') / 60,
            'months' => $months,
        ];
    }
}
