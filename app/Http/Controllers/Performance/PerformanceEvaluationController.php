<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Performance Evaluations page, where a Manager starts and writes the
 * Performance Evaluations of the Employees they effectively manage, and
 * whoever one of the Permissions for reading them
 * (Permission::seeingPerformanceEvaluations()) reaches reads them; and the
 * page of each Performance Evaluation.
 */
class PerformanceEvaluationController extends Controller
{
    /**
     * Show, for one year, the Employees whose Performance Evaluations the
     * viewer may open here (those they effectively manage, and those one of
     * the Permissions for reading them reaches) who work for the company on some
     * day of it, Former Employees included, by name, each with that year's
     * Performance Evaluation if they have one and whether the viewer may
     * start it. The years to choose from run back from the current one to
     * the earliest Start Date among those Employees.
     */
    public function index(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        $year = $request->year();
        $firstStart = Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->min('start_date');
        $years = range(today()->year, min(today()->year, $firstStart === null ? today()->year : CarbonImmutable::parse($firstStart)->year));

        return Inertia::render('performance-evaluations/Index', [
            'year' => $year,
            'years' => $years,
            'employees' => PageWithin::paginate(Employee::query()
                ->withPerformanceEvaluationsVisibleTo($actor)
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
                            'outcome' => $evaluation->completedOutcome()?->value,
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
     * viewer may give, change or remove them and their Outcomes. Outcomes
     * and the summary are left out for anyone but the effective Manager
     * until it is Completed. Whether it is the viewer's
     * own sets where its breadcrumbs lead: the Performance page, or
     * the Performance Evaluations page for the year.
     */
    public function show(PerformanceEvaluation $performanceEvaluation, #[CurrentUser] Employee $actor): Response
    {
        $seesOutcomes = $actor->can('seeOutcomes', $performanceEvaluation);

        return Inertia::render('performance-evaluations/Show', [
            'evaluation' => [
                'id' => $performanceEvaluation->id,
                'year' => $performanceEvaluation->year,
                'state' => $performanceEvaluation->state->value,
                'employee' => $performanceEvaluation->employee->only('id', 'name'),
                'goals' => $performanceEvaluation->goals()->withCount('comments')->get()
                    ->map(fn (Goal $goal): array => [
                        ...$goal->only('id', 'title', 'description'),
                        'progress' => $goal->progress->value,
                        'comments_count' => $goal->comments_count,
                        'outcome' => $seesOutcomes ? $goal->outcome?->value : null,
                    ])
                    ->all(),
                'outcome' => $seesOutcomes ? $performanceEvaluation->outcome?->value : null,
                'summary' => $seesOutcomes ? $performanceEvaluation->summary : null,
                'completed_at' => $performanceEvaluation->completed_at?->toDateString(),
            ],
            'isOwn' => $performanceEvaluation->employee_id === $actor->id,
            'canEdit' => $actor->can('edit', $performanceEvaluation),
            'canSeeOutcomes' => $seesOutcomes,
            'canReopen' => $actor->can('reopen', $performanceEvaluation),
        ]);
    }
}
