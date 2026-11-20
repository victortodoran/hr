<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * A Performance Evaluation's page with the Add Goal sheet open over it
 * (rule L14), as the props it renders.
 */
final class GoalFormPage
{
    /**
     * The Performance Evaluation's page props (PerformanceEvaluationPage),
     * and that the Add Goal sheet is open.
     *
     * @return array<string, mixed>
     */
    public static function create(PerformanceEvaluation $performanceEvaluation, Employee $actor): array
    {
        return [
            ...PerformanceEvaluationPage::props($performanceEvaluation, $actor),
            'addingGoal' => true,
        ];
    }
}
