<?php

namespace App\Pages\Performance;

use App\Models\Comment;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Models\ProgressChange;
use App\Support\PerformanceEvaluationSummary;

/**
 * A Goal's own page as the props it renders: the Goal with its Progress
 * and every change to it, and its Comments.
 */
final class GoalPage
{
    /**
     * The Goal with its Progress and every change to it, newest first, and
     * its Comments, oldest first, and whether the viewer may change the
     * Progress, write Comments, and change or remove the Goal.
     *
     * @return array<string, mixed>
     */
    public static function props(PerformanceEvaluation $performanceEvaluation, Goal $goal, Employee $actor): array
    {
        return [
            'evaluation' => PerformanceEvaluationSummary::of($performanceEvaluation),
            'goal' => [
                ...$goal->only('id', 'title', 'description'),
                'progress' => $goal->progress->value,
                'given_at' => $goal->created_at?->toDateString(),
            ],
            'history' => $goal->progressChanges()->with('changedBy:id,name')->get()
                ->map(fn (ProgressChange $change): array => [
                    'progress' => $change->progress->value,
                    'previous_progress' => $change->previous_progress->value,
                    'changed_by' => $change->changedBy->name,
                    'changed_at' => $change->created_at->toIso8601String(),
                ])
                ->all(),
            'comments' => $goal->comments()->with('author:id,name')->get()
                ->map(fn (Comment $comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->author->name,
                    'written_at' => $comment->created_at->toIso8601String(),
                ])
                ->all(),
            'isOwn' => $performanceEvaluation->employee_id === $actor->id,
            'canChangeProgress' => $actor->can('changeProgress', $performanceEvaluation),
            'canComment' => $actor->can('comment', $performanceEvaluation),
            'canEdit' => $actor->can('edit', $performanceEvaluation),
        ];
    }
}
