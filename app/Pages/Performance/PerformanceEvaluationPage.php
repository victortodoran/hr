<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;

/**
 * A Performance Evaluation's own page as the props it renders: the
 * Performance Evaluation with its Goals, and what the viewer may do there.
 */
final class PerformanceEvaluationPage
{
    /**
     * The Performance Evaluation with its Goals, and whether the viewer may
     * give, change or remove them and their Outcomes. Outcomes and the
     * summary are left out for anyone but the effective Manager until it is
     * Completed. Whether it is the viewer's own sets where its breadcrumbs
     * lead: the Performance page, or the Performance Evaluations page for
     * the year.
     *
     * @return array<string, mixed>
     */
    public static function props(PerformanceEvaluation $performanceEvaluation, Employee $actor): array
    {
        $seesOutcomes = $actor->can('seeOutcomes', $performanceEvaluation);

        return [
            'evaluation' => [
                'id' => $performanceEvaluation->id,
                'year' => $performanceEvaluation->year,
                'state' => $performanceEvaluation->state->value,
                'employee' => $performanceEvaluation->employee->only('id', 'name'),
                'goals' => $performanceEvaluation->goals()->withCount('comments')->get()
                    ->map(fn (Goal $goal): array => [
                        ...$goal->only('id', 'title', 'description'),
                        'progress' => $goal->progress->value,
                        'comments_count' => $goal->comments_count,
                        'outcome' => $seesOutcomes ? $goal->outcome?->value : null,
                    ])
                    ->all(),
                'outcome' => $seesOutcomes ? $performanceEvaluation->outcome?->value : null,
                'summary' => $seesOutcomes ? $performanceEvaluation->summary : null,
                'completed_at' => $performanceEvaluation->completed_at?->toDateString(),
            ],
            'isOwn' => $performanceEvaluation->employee_id === $actor->id,
            'canEdit' => $actor->can('edit', $performanceEvaluation),
            'canSeeOutcomes' => $seesOutcomes,
            'canReopen' => $actor->can('reopen', $performanceEvaluation),
        ];
    }
}
