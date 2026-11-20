<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PerformanceEvaluationReopeningController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    public function store(PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $this->assess->reopen($performanceEvaluation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation reopened.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
