<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalProgressRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Changing a Goal's Progress, which the Employee and their effective
 * Manager both do while the Performance Evaluation is Open. Every change
 * is kept, and saving leads back to the Goal's page.
 */
class GoalProgressController extends Controller
{
    /**
     * Change the Goal's Progress.
     */
    public function update(GoalProgressRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $goal->changeProgress($request->progress(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Progress changed.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }
}
