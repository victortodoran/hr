<?php

namespace App\Http\Controllers\Performance;

use App\Actions\ChangeGoal;
use App\Actions\GiveGoal;
use App\Actions\RemoveGoal;
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
 * effective Manager gives, changes or removes, while it is Open. Giving
 * and removing lead back to the Performance Evaluation's page. Each Goal
 * has its own page, open to everyone who can see the Evaluation, with
 * its Progress and every change to it, and its Comments; its Manager
 * changes its title and description there.
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
     * Give the Performance Evaluation a Goal. One Completed since the
     * policy check is refused, never given one.
     */
    public function store(GoalRequest $request, PerformanceEvaluation $performanceEvaluation, GiveGoal $giveGoal): RedirectResponse
    {
        $given = $giveGoal->give($performanceEvaluation, $request->goal());
        abort_if(! $given, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal given.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }

    /**
     * Show the Goal with its Progress and every change to it, newest
     * first, and its Comments, oldest first, and whether the viewer may
     * change the Progress, write Comments, and change or remove the Goal.
     * `?editing=1` opens it with the title and description as fields.
     */
    public function show(PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor, Request $request): Response
    {
        return Inertia::render('performance-evaluations/goals/Show', GoalPage::props($performanceEvaluation, $goal, $actor, $request->query('editing') === '1'));
    }

    /**
     * Change a Goal's title and description, which is done on its own
     * page, then lead back there. One whose Performance Evaluation was
     * Completed since the policy check is refused, never changed.
     */
    public function update(GoalRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, ChangeGoal $changeGoal): RedirectResponse
    {
        $changed = $changeGoal->change($goal, $request->goal());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal changed.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }

    /**
     * Remove a Goal from the Performance Evaluation. One whose
     * Performance Evaluation was Completed since the policy check is
     * refused, never removed.
     */
    public function destroy(PerformanceEvaluation $performanceEvaluation, Goal $goal, RemoveGoal $removeGoal): RedirectResponse
    {
        $removed = $removeGoal->remove($goal);
        abort_if(! $removed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal removed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
