<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Assignment;
use App\Models\Employee;

/**
 * Who may see and change Assignments. Each check is made against the
 * Employee the Assignments belong to, through the Gate of the Permission
 * behind it, so the acting Employee's Scope decides.
 */
class AssignmentPolicy
{
    /**
     * Seeing an Employee's Assignments needs view assignments for them, or
     * manage assignments, since managing them means seeing them.
     */
    public function viewAny(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewAssignments->value, $employee)
            || $this->create($actor, $employee);
    }

    /**
     * Putting an Employee on a Project needs manage assignments for them.
     */
    public function create(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageAssignments->value, $employee);
    }

    /**
     * Changing an Assignment needs manage assignments for its Employee.
     */
    public function update(Employee $actor, Assignment $assignment): bool
    {
        return $this->create($actor, $assignment->employee);
    }

    /**
     * The spare-time view lists other Employees, so it needs view or manage
     * assignments with a Scope reaching beyond the actor: reports or everyone.
     */
    public function viewSpareTime(Employee $actor): bool
    {
        return $actor->assignmentScopes()->contains(fn (Scope $scope): bool => $scope !== Scope::Own);
    }
}
