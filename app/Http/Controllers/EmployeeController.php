<?php

namespace App\Http\Controllers;

use App\Actions\AddEmployee;
use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets HR add Employees and keep their records up to date.
 */
class EmployeeController extends Controller
{
    /**
     * Show the form for adding an Employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/Create', $this->choices());
    }

    /**
     * Add an Employee and invite them to set their password.
     */
    public function store(EmployeeRequest $request, AddEmployee $addEmployee): RedirectResponse
    {
        /** @var array{name: string, email: string, department_id: int, manager_id: int, start_date: string} $details */
        $details = $request->validated();

        $employee = $addEmployee->add($details);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added. An invitation has been sent to :email.', ['email' => $employee->email])]);

        return to_route('employees.edit', $employee);
    }

    /**
     * Show the form for editing an Employee's record.
     */
    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'department_id' => $employee->department_id,
                'manager_id' => $employee->manager_id,
                'start_date' => $employee->start_date->toDateString(),
                'is_top' => $employee->is_top,
                'has_pending_invitation' => $employee->hasPendingInvitation(),
            ],
            ...$this->choices(except: $employee),
        ]);
    }

    /**
     * Save changes to an Employee's record.
     */
    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.edit', $employee);
    }

    /**
     * The Departments and possible Managers the form offers.
     *
     * @return array{departments: Collection<int, Department>, managers: Collection<int, Employee>}
     */
    private function choices(?Employee $except = null): array
    {
        $managers = Employee::query()->orderBy('name');

        if ($except !== null) {
            $managers->whereKeyNot($except->id);
        }

        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'managers' => $managers->get(['id', 'name']),
        ];
    }
}
