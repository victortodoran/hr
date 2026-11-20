<?php

namespace App\Http\Controllers\Employees;

use App\Enums\DirectoryTab;
use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\DirectoryListRequest;
use App\Models\Employee;
use App\Pages\Employees\DirectoryListPage;
use App\Pages\Employees\DirectoryPage;
use App\Pages\Employees\TeamPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets every Employee with view directory find colleagues and see where
 * anyone sits in the company, and a Manager see their Direct Reports. It never shows Assignments, dates or Pay,
 * though it links to someone's Employee page for viewers allowed to open it.
 */
class DirectoryController extends Controller
{
    /**
     * Show current Employees by name, with their internal Job Title,
     * optionally searched by part of a name; or, on the Reports tab, the
     * viewer's Direct Reports. Only the open tab's props are sent.
     */
    public function index(#[CurrentUser] Employee $actor, DirectoryListRequest $request): Response
    {
        $tabs = DirectoryTab::openableBy($actor);
        $tab = $request->tab($tabs);

        return Inertia::render('directory/Index', [
            'tab' => $tab,
            'tabs' => $tabs,
            ...match ($tab) {
                DirectoryTab::Everyone => DirectoryListPage::props($request->search()),
                DirectoryTab::Reports => TeamPage::props($actor),
            },
        ]);
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
