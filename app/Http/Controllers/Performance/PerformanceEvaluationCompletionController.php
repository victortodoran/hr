<?php

namespace App\Http\Controllers\Performance;

use App\Actions\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\AssessmentRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Completing a Performance Evaluation: its Manager saves the Outcome of
 * the whole and the summary and closes it, once it has Goals and every
 * Goal, the whole and the summary are given. It can't be changed after
 * that unless it's Reopened.
 */
class PerformanceEvaluationCompletionController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Save the assessment and Complete the Performance Evaluation, or
     * refuse, saving nothing, while anything is missing. One Completed
     * since the policy check is refused, never changed.
     */
    public function store(AssessmentRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $completed = $this->assess->complete($performanceEvaluation, $request->outcome(), $request->summary());
        abort_if(! $completed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation completed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
