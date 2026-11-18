<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Carbon\CarbonImmutable;

/**
 * The Performance Evaluations page as the props it renders: one year's
 * list of the Employees whose Performance Evaluations the viewer may open
 * here, each with that year's Performance Evaluation if they have one.
 */
final class PerformanceEvaluationListPage
{
    /**
     * The Employees whose Performance Evaluations the viewer may open here
     * (those they effectively manage, and those one of the Permissions for
     * reading them reaches) who work for the company on some day of the
     * year, Former Employees included, by name, each with that year's
     * Performance Evaluation if they have one and whether the viewer may
     * start it. The years to choose from run back from the current one to
     * the earliest Start Date among those Employees.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, int $year): array
    {
        $firstStart = Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->min('start_date');
        $years = range(today()->year, min(today()->year, $firstStart === null ? today()->year : CarbonImmutable::parse($firstStart)->year));

        return [
            'year' => $year,
            'years' => $years,
            'employees' => PageWithin::paginate(Employee::query()
                ->withPerformanceEvaluationsVisibleTo($actor)
                ->employedDuring($year)
                ->with(['performanceEvaluations' => fn ($evaluations) => $evaluations->where('year', $year)->withCount('goals')->withAtRiskGoalsCount()]) // @pest-mutate-ignore (eager loading only saves queries; the row reads the same year either way)
                ->orderBy('name')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(function (Employee $employee) use ($actor, $year): array {
                    $evaluation = $employee->performanceEvaluations->first();

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'evaluation' => $evaluation === null ? null : [
                            'id' => $evaluation->id,
                            'state' => $evaluation->state->value,
                            'goals_count' => $evaluation->goals_count,
                            'at_risk_goals_count' => $evaluation->openAtRiskGoalsCount(),
                            'outcome' => $evaluation->completedOutcome()?->value,
                        ],
                        'can_start' => $evaluation === null && $year === today()->year && $actor->can('create', [PerformanceEvaluation::class, $employee]),
                    ];
                }),
        ];
    }
}
