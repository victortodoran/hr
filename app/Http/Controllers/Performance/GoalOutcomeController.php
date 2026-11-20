<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\AssessPerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalOutcomeRequest;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;

/**
 * Giving, changing or clearing one Goal's Outcome, which the effective
 * Manager does from its card or its own page while the Performance
 * Evaluation is Open. Saving leads back to the page it was given on.
 */
class GoalOutcomeController extends Controller
{
    public function __construct(private readonly AssessPerformanceEvaluation $assess) {}

    /**
     * Give the Goal the Outcome sent, or clear it when none is. One whose
     * Performance Evaluation was Completed since the policy check is
     * refused, never changed.
     */
    public function update(GoalOutcomeRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal): RedirectResponse
    {
        $given = $this->assess->giveGoalOutcome($goal, $request->outcome());
        abort_if(! $given, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        return back(fallback: route('performance-evaluations.show', $performanceEvaluation));
    }
}
