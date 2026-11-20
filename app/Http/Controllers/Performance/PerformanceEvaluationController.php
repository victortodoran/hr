<?php

namespace App\Http\Controllers\Performance;

use App\Actions\Performance\CreatePerformanceEvaluation;
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

class PerformanceEvaluationController extends Controller
{
    public function index(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationListPage::props($actor, $request->filters()));
    }

    /** The form opens as a sheet over this page (rule L14), so it has a URL of its own. */
    public function create(PerformanceEvaluationListRequest $request, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Index', PerformanceEvaluationFormPage::create($actor, $request->filters()));
    }

    public function store(PerformanceEvaluationRequest $request, CreatePerformanceEvaluation $createPerformanceEvaluation): RedirectResponse
    {
        $evaluation = $createPerformanceEvaluation->create($request->employee(), $request->year());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Performance Evaluation created.')]);

        return to_route('performance-evaluations.show', $evaluation);
    }

    public function show(PerformanceEvaluation $performanceEvaluation, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Show', PerformanceEvaluationPage::props($performanceEvaluation, $actor));
    }
}
