<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Pages\Performance\MyPerformanceEvaluationsPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Performance page: the signed-in Employee's own
 * Performance Evaluations, which every Employee sees with no Permission
 * needed.
 */
class MyPerformanceEvaluationController extends Controller
{
    /**
     * Show the Employee's Performance Evaluation for this year as its own
     * page does, with its Goals and their Progress, and their others below
     * by year, newest first.
     */
    public function __invoke(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Mine', MyPerformanceEvaluationsPage::props($actor));
    }
}
