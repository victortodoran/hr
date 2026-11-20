<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\ChangeGoal;
use App\Actions\Performance\GiveGoal;
use App\Actions\Performance\RemoveGoal;
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

class GoalController extends Controller
{
    /** The form opens as a sheet over this page (rule L14), so it has a URL of its own. */
    public function create(PerformanceEvaluation $performanceEvaluation, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Show', GoalFormPage::create($performanceEvaluation, $actor));
    }

    public function store(GoalRequest $request, PerformanceEvaluation $performanceEvaluation, GiveGoal $giveGoal): RedirectResponse
    {
        $given = $giveGoal->give($performanceEvaluation, $request->goal());
        abort_if(! $given, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal added.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }

    public function show(PerformanceEvaluation $performanceEvaluation, Goal $goal, #[CurrentUser] Employee $actor, Request $request): Response
    {
        return Inertia::render('performance-evaluations/goals/Show', GoalPage::props($performanceEvaluation, $goal, $actor, $request->query('editing') === '1'));
    }

    public function update(GoalRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal, ChangeGoal $changeGoal): RedirectResponse
    {
        $changed = $changeGoal->change($goal, $request->goal());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal saved.')]);

        return to_route('performance-evaluations.goals.show', [$performanceEvaluation, $goal]);
    }

    public function destroy(PerformanceEvaluation $performanceEvaluation, Goal $goal, RemoveGoal $removeGoal): RedirectResponse
    {
        $removed = $removeGoal->remove($goal);
        abort_if(! $removed, 403); // @pest-mutate-ignore (only reached when the Performance Evaluation is Completed between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal deleted.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
    }
}
