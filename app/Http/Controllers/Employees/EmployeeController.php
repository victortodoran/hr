<?php

namespace App\Http\Controllers\Employees;

use App\Actions\Assignments\UpdateTrimmingAssignments;
use App\Actions\Employees\AddEmployee;
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

class EmployeeController extends Controller
{
    public function index(#[CurrentUser] Employee $actor, EmployeeListRequest $request): Response
    {
        return Inertia::render('employees/Index', EmployeeListPage::props($actor, $request->filters()));
    }

    public function create(): Response
    {
        return Inertia::render('employees/Create', EmployeeFormPage::create());
    }

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

    public function show(#[CurrentUser] Employee $actor, Employee $employee, Request $request): Response
    {
        $tab = $request->query('tab');

        return Inertia::render('employees/Show', EmployeePage::props($actor, $employee, is_string($tab) ? EmployeePageTab::tryFrom($tab) : null, Period::fromQuery($request->query()) ?? Period::month(today()->format('Y-m'))));
    }

    public function edit(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        return Inertia::render('employees/Edit', EmployeeFormPage::edit($actor, $employee));
    }

    /** A new Manager or Leaving Date changes both Employees' Reports, so their access is read afresh. */
    public function update(#[CurrentUser] Employee $actor, EmployeeRequest $request, Employee $employee, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateEmployee($employee, $request->validated());
        $actor->forgetAccess();
        $employee->forgetAccess();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return $actor->can('update', $employee) ? to_route('employees.edit', $employee) : to_route('employees.show', $employee);
    }
}
