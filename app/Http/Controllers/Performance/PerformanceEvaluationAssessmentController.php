<?php

namespace App\Http\Controllers\Performance;

use App\Actions\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\AssessmentRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Saving the Manager's Outcomes and summary on an Open Performance
 * Evaluation, bit by bit, before Completing it.
 */
class PerformanceEvaluationAssessmentController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Save the Outcomes and summary as given, any still empty. One
     * Completed since the policy check is refused, never changed.
     */
    public function update(AssessmentRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $saved = $this->assess->save($performanceEvaluation, $request->goalOutcomes(), $request->outcome(), $request->summary());
        abort_if(! $saved, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Outcomes saved.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
