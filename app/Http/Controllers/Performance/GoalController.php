<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\GoalFormPage;
use App\Pages\Performance\GoalPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Goals of a Performance Evaluation, which only the Employee's
 * effective Manager gives, changes or removes, while it is Open. Saving
 * and removing lead back to the Performance Evaluation's page. Each Goal
 * has its own page, open to everyone who can see the Evaluation, with
 * its Progress and every change to it, and its Comments.
 */
class GoalController extends Controller
{
    /**
     * Show the form for giving a Goal.
     */
    public function create(PerformanceEvaluation $performanceEvaluation): Response
    {
        return Inertia::render('performance-evaluations/goals/Create', GoalFormPage::create($performanceEvaluation));
    }

    /**
     * Give the Performance Evaluation a Goal.
     */
    public function store(GoalRequest $request, PerformanceEvaluation $performanceEvaluation): RedirectResponse
    {
        $performanceEvaluation->goals()->create($request->goal());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal given.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }

    /**
     * Show the Goal with its Progress and every change to it, newest
     * first, and its Comments, oldest first, and whether the viewer may
     * change the Progress, write Comments, and change or remove the Goal.
     */
    public function show(PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/goals/Show', GoalPage::props($performanceEvaluation, $goal, $actor));
    }

    /**
     * Show the form for changing a Goal, and whether it was opened from
     * the Goal's own page (`?from=goal`), where saving then leads back.
     */
    public function edit(Request $request, PerformanceEvaluation $performanceEvaluation, Goal $goal): Response
    {
        return Inertia::render('performance-evaluations/goals/Edit', GoalFormPage::edit($performanceEvaluation, $goal, $request->query('from') === 'goal'));
    }

    /**
     * Change a Goal's title and description, then lead back to where it
     * was changed from.
     */
    public function update(GoalRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal): RedirectResponse
    {
        $goal->update($request->goal());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal changed.')]);

        return $request->returnsToGoal()
            ? to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal])
            : to_route('performance-evaluations.show', $performanceEvaluation);
    }

    /**
     * Remove a Goal from the Performance Evaluation.
     */
    public function destroy(PerformanceEvaluation $performanceEvaluation, Goal $goal): RedirectResponse
    {
        $goal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal removed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
