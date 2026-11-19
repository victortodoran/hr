<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Http\Requests\Performance\PerformanceEvaluationRequest;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Pages\Performance\PerformanceEvaluationFormPage;
use App\Pages\Performance\PerformanceEvaluationListPage;
use App\Pages\Performance\PerformanceEvaluationPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Performance Evaluations page, where a Manager creates and writes the
 * Performance Evaluations of the Employees they effectively manage, and
 * whoever one of the Permissions for reading them
 * (Permission::seeingPerformanceEvaluations()) reaches reads them; the New
 * Performance Evaluation page; and the page of each Performance Evaluation.
 */
class PerformanceEvaluationController extends Controller
{
    /**
     * Show, for one year, the Employees whose Performance Evaluations the
     * viewer may open here (those they effectively manage, and those one of
     * the Permissions for reading them reaches) who have one for it,
     * Former Employees included, by name, with that Performance
     * Evaluation, and whether the viewer may create any. The years to
     * choose from run back from the next one to the earliest Start Date
     * among those Employees.
     */
    public function index(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationListPage::props($actor, $request->year()));
    }

    /**
     * Show the New Performance Evaluation page: the current and the next
     * year, each with the Employees the viewer may create one for.
     */
    public function create(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Create', PerformanceEvaluationFormPage::create($actor));
    }

    /**
     * Create the Employee's Performance Evaluation for the chosen year,
     * Open and with no Goals yet, and open its page.
     */
    public function store(PerformanceEvaluationRequest $request): RedirectResponse
    {
        $evaluation = $request->employee()->performanceEvaluations()->create(['year' => $request->year()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation created.')]);

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
