<?php

namespace App\Http\Controllers\Assignments;

use App\Actions\Assignments\ChangeAssignment;
use App\Actions\Assignments\DeleteAssignment;
use App\Enums\EmployeePageTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public function store(AssignmentRequest $request, Employee $employee): RedirectResponse
    {
        $employee->assignments()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment created.')]);

        return to_route('employees.show', ['employee' => $employee, 'tab' => EmployeePageTab::Assignments]);
    }

    public function update(AssignmentRequest $request, Assignment $assignment, ChangeAssignment $changeAssignment): RedirectResponse
    {
        $changeAssignment->change($assignment, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment saved.')]);

        return to_route('employees.show', ['employee' => $assignment->employee_id, 'tab' => EmployeePageTab::Assignments]);
    }

    public function destroy(Assignment $assignment, DeleteAssignment $deleteAssignment): RedirectResponse
    {
        $deleteAssignment->delete($assignment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return to_route('employees.show', ['employee' => $assignment->employee_id, 'tab' => EmployeePageTab::Assignments]);
    }
}
