<?php

namespace App\Http\Controllers;

use App\Enums\TimeOffState;
use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home, where every Employee lands after signing in: a summary of their
 * week, their Time Off and what waits for them to decide. Each block is
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
}
