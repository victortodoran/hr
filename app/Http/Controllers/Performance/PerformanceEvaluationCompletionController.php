<?php

namespace App\Http\Controllers\Performance;

use App\Actions\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\AssessmentRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Completing a Performance Evaluation: its Manager saves the Outcomes and
 * summary and closes it, once it has Goals and every Goal, the whole and
 * the summary are given. It can't be changed after that unless it's
 * Reopened.
 */
class PerformanceEvaluationCompletionController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Save the assessment and Complete the Performance Evaluation, or
     * refuse, saving nothing, while anything is missing.
     */
    public function store(AssessmentRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $this->assess->complete($performanceEvaluation, $request->goalOutcomes(), $request->outcome(), $request->summary());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation completed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
