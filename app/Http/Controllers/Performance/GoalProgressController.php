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

/**
 * Changing a Goal's Progress, which the Employee and their effective
 * Manager both do while the Performance Evaluation is Open. Every change
 * is kept, and saving leads back to the page it was changed on: the
 * Goal's own or its Performance Evaluation's.
 */
class GoalProgressController extends Controller
{
    public function __construct(private readonly ChangeGoalProgress $changeGoalProgress) {}

    /**
     * Change the Goal's Progress. One whose Performance Evaluation was
     * Completed since the policy check is refused with a 403 by
     * ChangeGoalProgress, never changed.
     */
    public function update(GoalProgressRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $this->changeGoalProgress->change($goal, $request->progress(), $actor);

        return back(fallback: route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]));
    }
}
