<?php

namespace App\Policies;

use App\Enums\Permission;
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
     * manage assignments, since managing them means seeing them. Pay belongs
     * to Assignments (ADR 0002), so seeing their Pay also shows them.
     */
    public function viewAny(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewAssignments->value, $employee)
            || $this->create($actor, $employee)
            || $this->viewPay($actor, $employee);
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
     * Seeing the Pay on an Employee's Assignments needs view pay for them,
     * or manage pay, since managing Pay means seeing it.
     */
    public function viewPay(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewPay->value, $employee)
            || $this->managePay($actor, $employee);
    }

    /**
     * Adding and removing Pay on an Employee's Assignments needs manage pay
     * for them.
     */
    public function managePay(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManagePay->value, $employee);
    }

    /**
     * Changing one Assignment's Pay needs manage pay for its Employee.
     */
    public function managePayOn(Employee $actor, Assignment $assignment): bool
    {
        return $this->managePay($actor, $assignment->employee);
    }
}
