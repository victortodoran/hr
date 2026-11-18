<?php

namespace App\Http\Controllers\Employees;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\DirectoryListRequest;
use App\Models\Employee;
use App\Pages\Employees\DirectoryListPage;
use App\Pages\Employees\DirectoryPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets every Employee with view directory find colleagues and see where
 * anyone sits in the company. It never shows Assignments, dates or Pay,
 * though it links to someone's Employee page for viewers allowed to open it.
 */
class DirectoryController extends Controller
{
    /**
     * Show current Employees by name, with their internal Job Title,
     * optionally searched by part of a name.
     */
    public function index(DirectoryListRequest $request): Response
    {
        return Inertia::render('directory/Index', DirectoryListPage::props($request->search()));
    }

    /**
     * Show an Employee's Reporting Chain: everyone above them up to one with
     * no Manager, and the Employees they are the Manager of.
     * Former Employees have left the directory, so they have no page.
     */
    public function show(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        abort_if($employee->employmentStatus() === EmploymentStatus::Former, 404);

        return Inertia::render('directory/Show', DirectoryPage::props($actor, $employee));
    }
}
