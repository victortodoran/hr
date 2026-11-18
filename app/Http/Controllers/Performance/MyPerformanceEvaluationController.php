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
     * Show the Employee's Performance Evaluations by year, newest first,
     * with their state, how many Goals each holds, how many of them are
     * At Risk while Open and, once Completed, the Outcome of the whole.
     */
    public function __invoke(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Mine', MyPerformanceEvaluationsPage::props($actor));
    }
}
