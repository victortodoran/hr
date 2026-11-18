<?php

namespace App\Http\Controllers\Employees;

use App\Actions\AddEmployee;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\EmployeePageTab;
use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Employee;
use App\Pages\Employees\EmployeeFormPage;
use App\Pages\Employees\EmployeeListPage;
use App\Pages\Employees\EmployeePage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
     * narrowed to one status or part of a name.
     */
    public function index(#[CurrentUser] Employee $actor, Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(EmploymentStatus::class)],
        ]);

        return Inertia::render('employees/Index', EmployeeListPage::props(
            $actor,
            $filters['search'] ?? '',
            isset($filters['status']) ? EmploymentStatus::from($filters['status']) : null,
        ));
    }

    /**
     * Show the form for adding an Employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/Create', EmployeeFormPage::create());
    }

    /**
     * Add an Employee and invite them to set their password.
     */
    public function store(#[CurrentUser] Employee $actor, EmployeeRequest $request, AddEmployee $addEmployee): RedirectResponse
    {
        /** @var array{name: string, email: string, department_id: int, manager_id: int, start_date: string} $details */
        $details = $request->validated();

        $employee = $addEmployee->add($actor, $details);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added. An invitation has been sent to :email.', ['email' => $employee->email])]);

        return to_route('employees.edit', $employee);
    }

    /**
     * Show an Employee's page on the tab asked for, as far as the viewer may
     * see it.
     */
    public function show(#[CurrentUser] Employee $actor, Employee $employee, Request $request): Response
    {
        $tab = $request->query('tab');

        return Inertia::render('employees/Show', EmployeePage::props($actor, $employee, is_string($tab) ? EmployeePageTab::tryFrom($tab) : null));
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
     * to end by their Leaving Date in the same transaction.
     */
    public function update(EmployeeRequest $request, Employee $employee, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateEmployee($employee, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.edit', $employee);
    }
}
