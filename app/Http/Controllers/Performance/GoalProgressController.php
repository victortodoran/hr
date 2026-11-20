<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\ChangeGoalProgress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalProgressRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class GoalProgressController extends Controller
{
    public function __construct(private readonly ChangeGoalProgress $changeGoalProgress) {}

    public function update(GoalProgressRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $this->changeGoalProgress->change($goal, $request->progress(), $actor);

        return back(fallback: route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]));
    }
}
