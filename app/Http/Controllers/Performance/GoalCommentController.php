<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\CommentOnGoal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalCommentRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GoalCommentController extends Controller
{
    public function store(GoalCommentRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor, CommentOnGoal $commentOnGoal): RedirectResponse
    {
        $written = $commentOnGoal->comment($goal, $actor, $request->body());
        abort_if(! $written, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }
}
