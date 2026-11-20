<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;

final class GoalFormPage
{
    /** @return array<string, mixed> */
    public static function create(PerformanceEvaluation $performanceEvaluation, Employee $actor): array
    {
        return [
            ...PerformanceEvaluationPage::props($performanceEvaluation, $actor),
            'addingGoal' => true,
        ];
    }
}
