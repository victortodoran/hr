<?php

namespace App\Pages;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Home as the props it renders: a summary of the Employee's hours this
 * month and this year, their Time Off and what waits for them to decide.
 * Each block is null when it doesn't apply to them.
 */
final class HomePage
{
    /**
     * The signed-in Employee's summary.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $employee): array
    {
        $waiting = $employee->waitingForDecision();
        $hasHours = $employee->canSeeWorkEntries() && ! $employee->hasNoManager();

        return [
            'hoursThisMonth' => $hasHours ? self::hoursThisMonth($employee) : null,
            'hoursThisYear' => $hasHours ? self::hoursThisYear($employee) : null,
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
            'declinedAhead' => self::declinedAhead($employee),
            'waitingForYou' => array_sum($waiting) > 0 ? $waiting : null,
        ];
    }

    /**
     * The Employee's Declined Time Off still to come, soonest first, so they
     * see why; null when there is none.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private static function declinedAhead(Employee $employee): ?array
    {
        $declined = $employee->timeOff()
            ->declined()
            ->whereDate('first_date', '>', today())
            ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->get();

        return $declined->isEmpty() ? null : $declined->map(fn (TimeOff $timeOff): array => $timeOff->details())->all();
    }

    /**
     * The Employee's hours this month, from its first day to today, as this
     * month's bar in hours this year counts them, leaving out Declined Work
     * Entries, and how many of those hours are Pending. Only for an
     * Employee who records hours and can see them: not one with no
     * Manager, who records no Work Entries.
     *
     * @return array{month: string, hours: float|int, pendingHours: float|int}
     */
    private static function hoursThisMonth(Employee $employee): array
    {
        $today = CarbonImmutable::today();
        $month = $today->startOfMonth();
        $entries = $employee->workEntries()
            ->counted()
            ->whereBetween('work_entries.date', [$month->toDateString(), $today->toDateString()])
            ->get();

        return [
            'month' => $month->format('Y-m'),
            'hours' => $entries->sum(fn (WorkEntry $workEntry): float|int => $workEntry->hours),
            'pendingHours' => $entries->where('state', WorkEntryState::Pending)->sum(fn (WorkEntry $workEntry): float|int => $workEntry->hours),
        ];
    }

    /**
     * The Employee's hours for each month of this year, leaving out Declined
     * Work Entries, with how many of those hours are Pending, and the year's
     * totals. The months run from January, or from their Start Date's month
     * when they started this year, to this month, those with no hours at 0;
     * the totals cover the same months. For the same Employees as hours
     * this month.
     *
     * @return array{year: int, hours: float|int, pendingHours: float|int, months: array<int, array{month: string, hours: float|int, pendingHours: float|int}>}
     */
    private static function hoursThisYear(Employee $employee): array
    {
        $today = CarbonImmutable::today();
        $january = $today->startOfYear();
        $first = $employee->start_date->isAfter($january) ? $employee->start_date->toImmutable()->startOfMonth() : $january;
        $minutes = $employee->workEntries()
            ->counted()
            ->whereBetween('work_entries.date', [$first->toDateString(), $today->toDateString()])
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
            'pendingHours' => (int) ($minutes[$month->format('Y-m')]->pending ?? 0) / 60,
        ], CarbonPeriod::create($first, '1 month', $today->startOfMonth())->toArray());

        return [
            'year' => $today->year,
            'hours' => (int) $minutes->sum('minutes') / 60,
            'pendingHours' => (int) $minutes->sum('pending') / 60,
            'months' => $months,
        ];
    }
}
