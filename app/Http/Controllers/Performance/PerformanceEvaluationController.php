<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\PerformanceEvaluationListPage;
use App\Pages\Performance\PerformanceEvaluationPage;
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
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationListPage::props($actor, $request->year()));
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
        return Inertia::render('performance-evaluations/Show', PerformanceEvaluationPage::props($performanceEvaluation, $actor));
    }
}
