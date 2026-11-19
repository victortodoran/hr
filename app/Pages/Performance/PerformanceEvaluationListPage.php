<?php

namespace App\Pages\Performance;

use App\Enums\PerformanceEvaluationListState;
use App\Enums\PerformanceEvaluationState;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Carbon\CarbonImmutable;

/**
 * The Performance Evaluations page as the props it renders: one year's
 * list of the Employees whose Performance Evaluations the viewer may open
 * here, each with their Performance Evaluation for it, or none yet.
 */
final class PerformanceEvaluationListPage
{
    /**
     * The Employees whose Performance Evaluations the viewer may open here
     * (those they effectively manage, and those one of the Permissions for
     * reading them reaches) who have one for the year, Former Employees
     * included, or are still due one, by name, optionally narrowed to part
     * of a name or one state. Each comes with that Performance Evaluation,
     * or with whether the viewer may create it. The years to choose from
     * run back from the next one to the earliest Start Date among those
     * Employees.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, int $year, string $search, ?PerformanceEvaluationListState $state): array
    {
        $latestYear = max(PerformanceEvaluation::creatableYears());
        $firstStart = Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->min('start_date');
        $years = range($latestYear, min(today()->year, $firstStart === null ? today()->year : CarbonImmutable::parse($firstStart)->year));

        $employees = PageWithin::paginate(Employee::query()
            ->withPerformanceEvaluationsVisibleTo($actor)
            ->where(fn (EmployeeBuilder $listed) => match ($state) {
                null => $listed->withPerformanceEvaluationFor($year)->orWhere(fn (EmployeeBuilder $due) => $due->withNoPerformanceEvaluationYetFor($year)),
                PerformanceEvaluationListState::Open => $listed->withPerformanceEvaluationFor($year, PerformanceEvaluationState::Open),
                PerformanceEvaluationListState::Completed => $listed->withPerformanceEvaluationFor($year, PerformanceEvaluationState::Completed),
                PerformanceEvaluationListState::NoneYet => $listed->withNoPerformanceEvaluationYetFor($year),
            })
            ->whereLike('name', '%'.addcslashes($search, '\\%_').'%')
            ->with(['performanceEvaluations' => fn ($evaluations) => $evaluations->where('year', $year)->withCount('goals')->withAtRiskGoalsCount()]) // @pest-mutate-ignore (eager loading only saves queries; the row reads the same year either way)
            ->orderBy('name')
            ->orderBy('id'), 25)
            ->withQueryString();

        $creatableIds = self::creatableIds($actor, $year, $employees->getCollection()->modelKeys());

        return [
            'year' => $year,
            'years' => $years,
            'search' => $search,
            'state' => $state?->value,
            'canCreate' => $actor->can('createAny', PerformanceEvaluation::class),
            'employees' => $employees->through(function (Employee $employee) use ($creatableIds): array {
                $evaluation = $employee->performanceEvaluations->first();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'can_create' => in_array($employee->id, $creatableIds, true),
                    'evaluation' => $evaluation === null ? null : [
                        'id' => $evaluation->id,
                        'state' => $evaluation->state->value,
                        'goals_count' => $evaluation->goals_count,
                        'at_risk_goals_count' => $evaluation->openAtRiskGoalsCount(),
                        'outcome' => $evaluation->completedOutcome()?->value,
                    ],
                ];
            }),
        ];
    }

    /**
     * Which of the given Employees the viewer may create a Performance
     * Evaluation for the year for, in one query for the whole page.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, int>
     */
    private static function creatableIds(Employee $actor, int $year, array $employeeIds): array
    {
        return Employee::query()
            ->awaitingPerformanceEvaluationFrom($actor, $year)
            ->whereKey($employeeIds)
            ->pluck('id')
            ->all();
    }
}
