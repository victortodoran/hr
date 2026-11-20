<?php

namespace App\Http\Controllers\Performance;

use App\Actions\CreatePerformanceEvaluation;
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
 * (Permission::seeingPerformanceEvaluations()) reaches reads them, with
 * the New Performance Evaluation dialog over it; and the page of each
 * Performance Evaluation.
 */
class PerformanceEvaluationController extends Controller
{
    /**
     * Show, for one year, the Employees whose Performance Evaluations the
     * viewer may open here (those they effectively manage, and those one of
     * the Permissions for reading them reaches) who have one for it,
     * Former Employees included, by name, with that Performance Evaluation,
     * optionally narrowed to part of a name, one Employee or one state.
     */
    public function index(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationListPage::props($actor, $request));
    }

    /**
     * Show the Performance Evaluations with the New Performance Evaluation
     * dialog open over them (rule L14), so the form has a URL of its own:
     * the current and the next year, each with the Employees the viewer may
     * create one for. The list behind the dialog keeps its year and filters.
     */
    public function create(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationFormPage::create($actor, $request));
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
