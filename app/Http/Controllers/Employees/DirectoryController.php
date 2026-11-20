<?php

namespace App\Http\Controllers\Employees;

use App\Enums\DirectoryTab;
use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\DirectoryListRequest;
use App\Models\Employee;
use App\Pages\Employees\DirectoryIndexPage;
use App\Pages\Employees\DirectoryPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
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
     * `?person=` opens an Employee's Reporting Chain in a sheet over the
     * list (rule K8): everyone above them up to one with no Manager, and
     * the Employees they are the Manager of.
     */
    public function index(#[CurrentUser] Employee $actor, DirectoryListRequest $request): Response
    {
        $tabs = DirectoryTab::openableBy($actor);

        return Inertia::render('directory/Index', [
            ...DirectoryIndexPage::props($actor, $request->tab($tabs), $tabs, $request->search()),
            ...DirectoryPage::props($actor, $request->person()),
        ]);
    }

    /**
     * Send an old link to an Employee's directory page to their sheet over
     * the Directory. Former Employees have left the directory, so they have
     * none.
     */
    public function show(Employee $employee): RedirectResponse
    {
        abort_if($employee->employmentStatus() === EmploymentStatus::Former, 404);

        return to_route('directory.index', ['person' => $employee->id]);
    }
}
