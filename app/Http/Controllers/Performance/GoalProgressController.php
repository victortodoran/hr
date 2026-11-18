<?php

namespace App\Http\Controllers\Performance;

use App\Actions\ChangeGoalProgress;
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
    public function __construct(private readonly ChangeGoalProgress $changeGoalProgress) {}

    /**
     * Change the Goal's Progress.
     */
    public function update(GoalProgressRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $this->changeGoalProgress->change($goal, $request->progress(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Progress changed.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }
}
