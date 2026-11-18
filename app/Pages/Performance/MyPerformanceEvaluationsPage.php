<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * The Performance page as the props it renders: the signed-in Employee's
 * own Performance Evaluations.
 */
final class MyPerformanceEvaluationsPage
{
    /**
     * The Employee's Performance Evaluations by year, newest first, with
     * their state, how many Goals each holds, how many of them are At Risk
     * while Open and, once Completed, the Outcome of the whole.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor): array
    {
        return [
            'evaluations' => $actor->performanceEvaluations()
                ->withCount('goals')
                ->withAtRiskGoalsCount()
                ->orderByDesc('year')
                ->get()
                ->map(fn (PerformanceEvaluation $evaluation): array => [
                    'id' => $evaluation->id,
                    'year' => $evaluation->year,
                    'state' => $evaluation->state->value,
                    'goals_count' => $evaluation->goals_count,
                    'at_risk_goals_count' => $evaluation->openAtRiskGoalsCount(),
                    'outcome' => $evaluation->completedOutcome()?->value,
                ])
                ->all(),
        ];
    }
}
