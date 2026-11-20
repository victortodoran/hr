<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\SummaryRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Saving the Manager's summary of an Open Performance Evaluation, before
 * Completing it. The Employee reads it only once it is
 * Completed. Saving leads back to the Performance Evaluation's page.
 */
class PerformanceEvaluationSummaryController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Save the summary as given, or clear it when empty. One Completed
     * since the policy check is refused, never changed.
     */
    public function update(SummaryRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $saved = $this->assess->writeSummary($performanceEvaluation, $request->summary());
        abort_if(! $saved, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Summary saved.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
