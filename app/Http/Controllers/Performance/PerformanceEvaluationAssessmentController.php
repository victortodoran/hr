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
     * Save the Outcomes and summary as given, any still empty.
     */
    public function update(AssessmentRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $this->assess->save($performanceEvaluation, $request->goalOutcomes(), $request->outcome(), $request->summary());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Outcomes saved.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
