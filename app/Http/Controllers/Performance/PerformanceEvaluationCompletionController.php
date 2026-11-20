<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\CompletionRequest;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PerformanceEvaluationCompletionController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    public function store(CompletionRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $completed = $this->assess->complete($performanceEvaluation, $request->outcome());
        abort_if(! $completed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation completed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
