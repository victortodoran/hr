<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Pages\Employees\TeamPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Team page: a Manager's Direct Reports, the Employees one level below
 * them in the Reporting Chain.
 */
class TeamController extends Controller
{
    /**
     * Show the viewer's Direct Reports by name, with their internal Job
     * Title and Department, and whether the viewer may open each one's
     * Employee page.
     */
    public function __invoke(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('team/Index', TeamPage::props($actor));
    }
}
