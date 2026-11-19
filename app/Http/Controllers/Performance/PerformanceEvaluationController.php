<?php

namespace App\Http\Controllers\Performance;

use App\Actions\CreatePerformanceEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\NewPerformanceEvaluationRequest;
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
     * Former Employees included, or are still due one, by name, with that
     * Performance Evaluation or a way to create it, optionally narrowed to
     * part of a name, one Employee or one state.
     */
    public function index(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationListPage::props($actor, $request->year(), $request->search(), $request->state(), $request->employeeId()));
    }

    /**
     * Show the New Performance Evaluation page: the current and the next
     * year, each with the Employees the viewer may create one for, with
     * the year and Employee in the query string chosen when they're among
     * them.
     */
    public function create(NewPerformanceEvaluationRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Create', PerformanceEvaluationFormPage::create($actor, $request->year(), $request->employeeId()));
    }

    /**
     * Create the Employee's Performance Evaluation for the chosen year,
     * Open and with no Goals yet, and open its page.
     */
    public function store(PerformanceEvaluationRequest $request, CreatePerformanceEvaluation $createPerformanceEvaluation): RedirectResponse
    {
        $evaluation = $createPerformanceEvaluation->create($request->employee(), $request->year());

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
