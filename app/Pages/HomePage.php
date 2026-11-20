<?php

namespace App\Pages;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Support\PaidDaysLeft;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class HomePage
{
    private const int NEEDS_ATTENTION_LIMIT = 10;

    /**
     * `hasNoManager` is only true when the Employee can see Work Entries.
     *
     * @return array{hasNoManager: bool, hoursThisMonth: array<string, mixed>|null, hoursThisYear: array<string, mixed>|null, paidDaysLeft: list<array{year: int, days: int}>, nextTimeOff: array<string, mixed>|null, needsAttention: array{items: list<array<string, mixed>>, more: int}|null, waitingForYou: array{workEntries: int, timeOff: int, travelRequests: int, travelExpenses: int}|null}
     */
    public static function props(Employee $employee): array
    {
        $waiting = $employee->waitingForDecision();
        $hasNoManager = $employee->canSeeWorkEntries() && $employee->hasNoManager();
        $hasHours = $employee->canSeeWorkEntries() && ! $hasNoManager;

        return [
            'hasNoManager' => $hasNoManager,
            'hoursThisMonth' => $hasHours ? self::hoursThisMonth($employee) : null,
            'hoursThisYear' => $hasHours ? self::hoursThisYear($employee) : null,
            'paidDaysLeft' => PaidDaysLeft::of($employee),
            'nextTimeOff' => $employee->timeOff()
                ->standing()
                ->whereDate('last_date', '>=', today())
                ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('first_date')
                ->first()
                ?->details(),
            'needsAttention' => self::needsAttention($employee),
            'waitingForYou' => array_sum($waiting) > 0 ? $waiting : null,
        ];
    }

    /**
     * A Work Entry or Travel Expense leaves once changed or deleted, Time Off and a Travel Request once they have started.
     *
     * @return array{items: list<array<string, mixed>>, more: int}|null
     */
    private static function needsAttention(Employee $employee): ?array
    {
        $kinds = [
            ...($employee->canSeeWorkEntries() ? [self::firstFewAndMore(
                $employee->workEntries()->declined(),
                'work_entries.date',
                ['assignment.project:id,name'],
                fn (WorkEntry $workEntry): array => [
                    'kind' => 'work-entry',
                    'id' => $workEntry->id,
                    'date' => $workEntry->date->toDateString(),
                    'project' => $workEntry->assignment->project->name,
                    'hours' => $workEntry->hours,
                    'decline_reason' => $workEntry->decline_reason,
                ],
            )] : []),
            self::firstFewAndMore(
                $employee->travelRequests()->declined()->whereDate('first_date', '>', today()),
                'first_date',
                [],
                fn (TravelRequest $travelRequest): array => [
                    'kind' => 'travel-request',
                    'id' => $travelRequest->id,
                    'destination' => $travelRequest->destination,
                    'first_date' => $travelRequest->first_date->toDateString(),
                    'last_date' => $travelRequest->last_date->toDateString(),
                    'decline_reason' => $travelRequest->decline_reason,
                ],
            ),
            self::firstFewAndMore(
                $employee->travelExpenses()->declined(),
                'travel_expenses.paid_on',
                ['travelRequest:id,destination'],
                fn (TravelExpense $travelExpense): array => [
                    'kind' => 'travel-expense',
                    'id' => $travelExpense->id,
                    'travel_request_id' => $travelExpense->travel_request_id,
                    'destination' => $travelExpense->travelRequest->destination,
                    'description' => $travelExpense->description,
                    'decline_reason' => $travelExpense->decline_reason,
                ],
            ),
            self::firstFewAndMore(
                $employee->timeOff()->declined()->whereDate('first_date', '>', today()),
                'first_date',
                [],
                fn (TimeOff $timeOff): array => [
                    'kind' => 'time-off',
                    'id' => $timeOff->id,
                    'first_date' => $timeOff->first_date->toDateString(),
                    'last_date' => $timeOff->last_date->toDateString(),
                    'decline_reason' => $timeOff->decline_reason,
                ],
            ),
        ];
        $items = array_merge(...array_column($kinds, 'items'));

        return $items === [] ? null : ['items' => $items, 'more' => array_sum(array_column($kinds, 'more'))];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>|Relation<TModel, *, *>  $query
     * @param  array<int, string>  $with
     * @param  callable(TModel): array<string, mixed>  $shape
     * @return array{items: list<array<string, mixed>>, more: int}
     */
    private static function firstFewAndMore(Builder|Relation $query, string $dateColumn, array $with, callable $shape): array
    {
        $listed = (clone $query)
            ->with($with) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy($dateColumn)
            ->limit(self::NEEDS_ATTENTION_LIMIT)
            ->get();
        $more = $listed->count() < self::NEEDS_ATTENTION_LIMIT ? 0 : $query->count() - self::NEEDS_ATTENTION_LIMIT;

        return ['items' => array_values($listed->map($shape)->all()), 'more' => $more];
    }

    /**
     * Counts as this month's bar in hours this year does, so the two agree.
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

    /** @return array{year: int, hours: float|int, pendingHours: float|int, months: array<int, array{month: string, hours: float|int, pendingHours: float|int}>} */
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
