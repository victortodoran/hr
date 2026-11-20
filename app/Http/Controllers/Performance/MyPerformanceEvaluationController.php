<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Pages\Performance\MyPerformanceEvaluationsPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class MyPerformanceEvaluationController extends Controller
{
    public function __invoke(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('performance-evaluations/Mine', MyPerformanceEvaluationsPage::props($actor));
    }
}
