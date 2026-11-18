<?php

namespace App\Http\Controllers\Performance;

use App\Actions\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\AssessmentRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Completing a Performance Evaluation: its Manager saves the Outcomes and
 * summary and closes it, once it has Goals and every Goal, the whole and
 * the summary are given. Nothing on it can be changed after that.
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
        if (! $performanceEvaluation->goals()->exists()) {
            throw ValidationException::withMessages(['outcome' => __('Give at least one Goal before completing.')]);
        }

        if (! $this->assess->complete($performanceEvaluation, $request->goalOutcomes(), $request->outcome(), $request->summary())) {
            throw ValidationException::withMessages(['outcome' => __('Give every Goal and the whole an Outcome, and write a summary, before completing.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation completed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
