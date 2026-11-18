<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Performance Evaluations page, where a Manager starts and writes the
 * Performance Evaluations of the Employees they effectively manage, and
 * the page of each Performance Evaluation.
 */
class PerformanceEvaluationController extends Controller
{
    /**
     * Show, for one year, the Employees the viewer effectively manages who
     * work for the company on some day of it, Former Employees included,
     * by name, each with that year's Performance Evaluation if they have
     * one and whether the viewer may start it. The years to choose from are
     * those any of these Employees has a Performance Evaluation for, and the
     * current one.
     */
    public function index(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        $year = $request->year();
        $years = PerformanceEvaluation::query()
            ->whereIn('employee_id', Employee::query()->effectivelyManagedBy($actor)->select('employees.id'))
            ->distinct()
            ->pluck('year')
            ->push(today()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return Inertia::render('performance-evaluations/Index', [
            'year' => $year,
            'years' => $years,
            'employees' => PageWithin::paginate(Employee::query()
                ->effectivelyManagedBy($actor)
                ->employedDuring($year)
                ->with(['performanceEvaluations' => fn ($evaluations) => $evaluations->where('year', $year)->withCount('goals')]) // @pest-mutate-ignore (eager loading only saves queries; the row reads the same year either way)
                ->orderBy('name')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(function (Employee $employee) use ($actor, $year): array {
                    $evaluation = $employee->performanceEvaluations->first();

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'evaluation' => $evaluation === null ? null : [
                            'id' => $evaluation->id,
                            'state' => $evaluation->state->value,
                            'goals_count' => $evaluation->goals_count,
                        ],
                        'can_start' => $evaluation === null && $year === today()->year && $actor->can('create', [PerformanceEvaluation::class, $employee]),
                    ];
                }),
        ]);
    }

    /**
     * Start the Employee's Performance Evaluation for the current year,
     * Open and with no Goals yet, and open its page.
     */
    public function store(Employee $employee): RedirectResponse
    {
        $evaluation = $employee->performanceEvaluations()->create(['year' => today()->year]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation started.')]);

        return to_route('performance-evaluations.show', $evaluation);
    }

    /**
     * Show one Performance Evaluation with its Goals, and whether the
     * viewer may give, change or remove them. Whether it is the viewer's
     * own sets where its breadcrumbs lead: My Performance Evaluations, or
     * the Performance Evaluations page for the year.
     */
    public function show(PerformanceEvaluation $performanceEvaluation, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Show', [
            'evaluation' => [
                'id' => $performanceEvaluation->id,
                'year' => $performanceEvaluation->year,
                'state' => $performanceEvaluation->state->value,
                'employee' => $performanceEvaluation->employee->only('id', 'name'),
                'goals' => $performanceEvaluation->goals
                    ->map(fn (Goal $goal): array => $goal->only('id', 'title', 'description'))
                    ->all(),
            ],
            'isOwn' => $performanceEvaluation->employee_id === $actor->id,
            'canManageGoals' => $actor->can('manageGoals', $performanceEvaluation),
        ]);
    }
}
