<?php

namespace App\Http\Controllers\Performance;

use App\Actions\CommentOnGoal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalCommentRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Writing Comments on a Goal, which the Employee and their effective
 * Manager both do while the Performance Evaluation is Open. A Comment is
 * never changed or removed on its own, so writing is all there is.
 */
class GoalCommentController extends Controller
{
    /**
     * Write a Comment on the Goal and lead back to the Goal's page. One
     * whose Performance Evaluation was Completed since the policy check
     * is refused, never written.
     */
    public function store(GoalCommentRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor, CommentOnGoal $commentOnGoal): RedirectResponse
    {
        $written = $commentOnGoal->comment($goal, $actor, $request->body());
        abort_if(! $written, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }
}
