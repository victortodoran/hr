<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Reopening a Completed Performance Evaluation: sending it back to Open so
 * its Manager can correct it and Complete it again. Its Goals, Outcomes
 * and summary stay as they were, hidden again from the Employee until then.
 * Only a Role that grants reopening reopens one; being the Manager is not
 * enough, and nobody reopens their own.
 */
class PerformanceEvaluationReopeningController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Send the Completed Performance Evaluation back to Open.
     */
    public function store(PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $this->assess->reopen($performanceEvaluation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation reopened.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
