<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * The Performance page as the props it renders: the signed-in Employee's
 * own Performance Evaluation for this year, as its own page shows it, and
 * their others by year.
 */
final class MyPerformanceEvaluationsPage
{
    /**
     * This year, and the Employee's Performance Evaluation for it, with the
     * same props its own page has, or null while they have none. Below it,
     * their Performance Evaluations for every other year, newest first,
     * with their state, how many Goals each holds, how many of them are At
     * Risk while Open and, once Completed, the Outcome of the whole.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor): array
    {
        $year = today()->year;
        $current = $actor->performanceEvaluations()->where('year', $year)->first()?->setRelation('employee', $actor);

        return [
            'year' => $year,
            'current' => $current === null ? null : PerformanceEvaluationPage::props($current, $actor),
            'evaluations' => $actor->performanceEvaluations()
                ->where('year', '!=', $year)
                ->withCount('goals')
                ->withAtRiskGoalsCount()
                ->orderByDesc('year')
                ->get()
                ->map(fn (PerformanceEvaluation $evaluation): array => $evaluation->listed())
                ->all(),
        ];
    }
}
