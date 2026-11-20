<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;

final class MyPerformanceEvaluationsPage
{
    /** @return array<string, mixed> */
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
