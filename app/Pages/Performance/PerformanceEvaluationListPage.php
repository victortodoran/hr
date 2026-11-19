<?php

namespace App\Pages\Performance;

use App\Models\Builders\PerformanceEvaluationBuilder;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Carbon\CarbonImmutable;

/**
 * The Performance Evaluations page as the props it renders: one year's
 * list of the Employees whose Performance Evaluations the viewer may open
 * here who have one for it, each with that Performance Evaluation.
 */
final class PerformanceEvaluationListPage
{
    /**
     * The Employees whose Performance Evaluations the viewer may open here
     * (those they effectively manage, and those one of the Permissions for
     * reading them reaches) who have one for the year, Former Employees
     * included, by name, each with that Performance Evaluation, and whether
     * the viewer may create any. The years to choose from run back from the
     * next one to the earliest Start Date among those Employees.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, int $year): array
    {
        $latestYear = max(PerformanceEvaluation::creatableYears());
        $firstStart = Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->min('start_date');
        $years = range($latestYear, min(today()->year, $firstStart === null ? today()->year : CarbonImmutable::parse($firstStart)->year));

        return [
            'year' => $year,
            'years' => $years,
            'canCreate' => $actor->can('createAny', PerformanceEvaluation::class),
            'employees' => PageWithin::paginate(Employee::query()
                ->withPerformanceEvaluationsVisibleTo($actor)
                ->whereHas('performanceEvaluations', fn (PerformanceEvaluationBuilder $evaluations) => $evaluations->where('year', $year))
                ->with(['performanceEvaluations' => fn ($evaluations) => $evaluations->where('year', $year)->withCount('goals')->withAtRiskGoalsCount()]) // @pest-mutate-ignore (eager loading only saves queries; the row reads the same year either way)
                ->orderBy('name')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(function (Employee $employee): array {
                    $evaluation = $employee->performanceEvaluations->sole();

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'evaluation' => [
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
}
