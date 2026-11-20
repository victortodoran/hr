<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\CompletionRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Completing a Performance Evaluation: its Manager gives the whole its
 * Outcome, which closes it, once it has Goals, every Goal has an Outcome
 * and the summary is written. It can't be changed after that unless it's
 * Reopened.
 */
class PerformanceEvaluationCompletionController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Give the whole its Outcome and Complete the Performance Evaluation,
     * or refuse, saving nothing, while anything is missing. One Completed
     * since the policy check is refused, never changed.
     */
    public function store(CompletionRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $completed = $this->assess->complete($performanceEvaluation, $request->outcome());
        abort_if(! $completed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation completed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
