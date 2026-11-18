<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The My Performance Evaluations page: the signed-in Employee's own
 * Performance Evaluations, which every Employee sees with no Permission
 * needed.
 */
class MyPerformanceEvaluationController extends Controller
{
    /**
     * Show the Employee's Performance Evaluations by year, newest first,
     * with their state, how many Goals each holds and, once Completed,
     * the Outcome of the whole.
     */
    public function __invoke(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Mine', [
            'evaluations' => $actor->performanceEvaluations()
                ->withCount('goals')
                ->orderByDesc('year')
                ->get()
                ->map(fn (PerformanceEvaluation $evaluation): array => [
                    'id' => $evaluation->id,
                    'year' => $evaluation->year,
                    'state' => $evaluation->state->value,
                    'goals_count' => $evaluation->goals_count,
                    'outcome' => $evaluation->completedOutcome()?->value,
                ])
                ->all(),
        ]);
    }
}
