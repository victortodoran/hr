<?php

namespace App\Http\Controllers\Assignments;

use App\Actions\Assignments\ChangeAssignment;
use App\Actions\Assignments\DeleteAssignment;
use App\Enums\EmployeePageTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets those who manage Assignments put Employees on Projects, change
 * their Assignments and delete one made by mistake. The Assignments
 * themselves are shown on the Employee page.
 */
class AssignmentController extends Controller
{
    /**
     * Put the Employee on a Project.
     */
    public function store(AssignmentRequest $request, Employee $employee): RedirectResponse
    {
        $employee->assignments()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment created.')]);

        return to_route('employees.show', ['employee' => $employee, 'tab' => EmployeePageTab::Assignments]);
    }

    /**
     * Change an Assignment's Job Title, dates or Capacity, including ending it
     * early, which removes the Pay changes dated after its new end.
     */
    public function update(AssignmentRequest $request, Assignment $assignment, ChangeAssignment $changeAssignment): RedirectResponse
    {
        $changeAssignment->change($assignment, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment saved.')]);

        return to_route('employees.show', ['employee' => $assignment->employee_id, 'tab' => EmployeePageTab::Assignments]);
    }

    /**
     * Delete an Assignment made by mistake, its Pay with it, refusing one
     * with Work Entries or Travel Requests and the Internal Assignment.
     */
    public function destroy(Assignment $assignment, DeleteAssignment $deleteAssignment): RedirectResponse
    {
        $deleteAssignment->delete($assignment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return to_route('employees.show', ['employee' => $assignment->employee_id, 'tab' => EmployeePageTab::Assignments]);
    }
}
