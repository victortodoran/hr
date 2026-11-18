<?php

namespace App\Http\Controllers\Performance;

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
     * Write a Comment on the Goal and lead back to the Goal's page.
     */
    public function store(GoalCommentRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $goal->comments()->create(['author_id' => $actor->id, 'body' => $request->body()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }
}
