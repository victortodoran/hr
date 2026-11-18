<?php

namespace App\Pages\Performance;

use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Support\PerformanceEvaluationSummary;

/**
 * The forms for giving a Goal and changing one, as the props they render:
 * whose Performance Evaluation it is and for which year, and when changing,
 * the Goal.
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
            'evaluation' => PerformanceEvaluationSummary::of($performanceEvaluation),
        ];
    }

    /**
     * The form for changing the Goal, and whether it was opened from the
     * Goal's own page, where saving then leads back.
     *
     * @return array<string, mixed>
     */
    public static function edit(PerformanceEvaluation $performanceEvaluation, Goal $goal, bool $returnsToGoal): array
    {
        return [
            'evaluation' => PerformanceEvaluationSummary::of($performanceEvaluation),
            'goal' => $goal->only('id', 'title', 'description'),
            'returnsToGoal' => $returnsToGoal,
        ];
    }
}
