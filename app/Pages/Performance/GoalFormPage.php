<?php

namespace App\Pages\Performance;

use App\Models\PerformanceEvaluation;
use App\Support\PerformanceEvaluationHeading;

/**
 * The form for giving a Goal, as the props it renders: whose Performance
 * Evaluation it is and for which year.
 */
final class GoalFormPage
{
    /**
     * The form for giving the Performance Evaluation a Goal.
     *
     * @return array<string, mixed>
     */
    public static function create(PerformanceEvaluation $performanceEvaluation): array
    {
        return [
            'evaluation' => PerformanceEvaluationHeading::of($performanceEvaluation),
        ];
    }
}
