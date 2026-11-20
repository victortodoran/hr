<?php

namespace App\Http\Controllers\Employees;

use App\Actions\AddEmployee;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\EmployeePageTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeListRequest;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Employee;
use App\Pages\Employees\EmployeeFormPage;
use App\Pages\Employees\EmployeeListPage;
use App\Pages\Employees\EmployeePage;
use App\Support\Period;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists, shows, adds and edits Employees, each as far as the viewer's
 * Permissions and Scope allow.
 */
class EmployeeController extends Controller
{
    /**
     * List the Employees whose record the viewer may see, optionally
     * narrowed to part of a name, a Department, a Manager and a status.
     */
    public function index(#[CurrentUser] Employee $actor, EmployeeListRequest $request): Response
    {
        return Inertia::render('employees/Index', EmployeeListPage::props($actor, $request->search(), $request->status(), $request->departmentId(), $request->managerId()));
    }

    /**
     * Show the form for adding an Employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/Create', EmployeeFormPage::create());
    }

    /**
     * Add an Employee and invite them to set their password, then show
     * their Employee page, or, when the actor may add Employees but not see
     * this one, the form to add another.
     */
    public function store(#[CurrentUser] Employee $actor, EmployeeRequest $request, AddEmployee $addEmployee): RedirectResponse
    {
        /** @var array{name: string, email: string, department_id: int, manager_id: int|null, start_date: string, leaving_date?: string|null, job_title: string} $details */
        $details = $request->validated();

        $employee = $addEmployee->add($actor, $details);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added. An invitation has been sent to :email.', ['email' => $employee->email])]);

        return $actor->can('view', $employee)
            ? to_route('employees.show', $employee)
            : to_route('employees.create');
    }

    /**
     * Show an Employee's page on the tab asked for, as far as the viewer may
     * see it, with the Work and Travel tabs on the Period asked for.
     */
    public function show(#[CurrentUser] Employee $actor, Employee $employee, Request $request): Response
    {
        $tab = $request->query('tab');

        return Inertia::render('employees/Show', EmployeePage::props($actor, $employee, is_string($tab) ? EmployeePageTab::tryFrom($tab) : null, Period::fromQuery($request->query())));
    }

    /**
     * Show the form for editing an Employee's record.
     */
    public function edit(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        return Inertia::render('employees/Edit', EmployeeFormPage::edit($actor, $employee));
    }

    /**
     * Save changes to an Employee's record. Their Assignments are trimmed
     * to end by their Leaving Date in the same transaction. The form opens
     * again, or their page when the change, such as a new Manager, took
     * them out of what the viewer may change.
     */
    public function update(#[CurrentUser] Employee $actor, EmployeeRequest $request, Employee $employee, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateEmployee($employee, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return $actor->can('update', $employee) ? to_route('employees.edit', $employee) : to_route('employees.show', $employee);
    }
}
