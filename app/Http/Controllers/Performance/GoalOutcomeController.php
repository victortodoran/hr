<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalOutcomeRequest;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;

class GoalOutcomeController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    public function update(GoalOutcomeRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal): RedirectResponse
    {
        $given = $this->assess->giveGoalOutcome($goal, $request->outcome());
        abort_if(! $given, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        return back(fallback: route('performance-evaluations.show', $performanceEvaluation));
    }
}
