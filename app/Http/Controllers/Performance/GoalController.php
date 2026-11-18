<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\GoalRequest;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Goals of a Performance Evaluation, which only the Employee's
 * effective Manager gives, changes or removes, while it is Open. Saving
 * and removing lead back to the Performance Evaluation's page.
 */
class GoalController extends Controller
{
    /**
     * Show the form for giving a Goal.
     */
    public function create(PerformanceEvaluation $performanceEvaluation): Response
    {
        return Inertia::render('performance-evaluations/goals/Create', [
            'evaluation' => $this->evaluationSummary($performanceEvaluation),
        ]);
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
     * Show the form for changing a Goal.
     */
    public function edit(PerformanceEvaluation $performanceEvaluation, Goal $goal): Response
    {
        return Inertia::render('performance-evaluations/goals/Edit', [
            'evaluation' => $this->evaluationSummary($performanceEvaluation),
            'goal' => $goal->only('id', 'title', 'description'),
        ]);
    }

    /**
     * Change a Goal's title and description.
     */
    public function update(GoalRequest $request, PerformanceEvaluation $performanceEvaluation, Goal $goal): RedirectResponse
    {
        $goal->update($request->goal());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal changed.')]);

        return to_route('performance-evaluations.show', $performanceEvaluation);
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

    /**
     * What the Goal forms show of the Performance Evaluation: whose it is
     * and for which year.
     *
     * @return array{id: int, year: int, employee: array{id: int, name: string}}
     */
    private function evaluationSummary(PerformanceEvaluation $performanceEvaluation): array
    {
        return [
            'id' => $performanceEvaluation->id,
            'year' => $performanceEvaluation->year,
            'employee' => $performanceEvaluation->employee->only('id', 'name'),
        ];
    }
}
