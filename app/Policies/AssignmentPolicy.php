<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\Employee;

class AssignmentPolicy
{
    public function viewAny(Employee $actor, Employee $employee): bool
    {
        return $actor->reachesThrough(Permission::seeingAssignments(), $employee);
    }

    public function create(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageAssignments->value, $employee);
    }

    public function update(Employee $actor, Assignment $assignment): bool
    {
        return $this->create($actor, $assignment->employee);
    }

    /** Whether it can be deleted at all is Assignment::canBeDeleted(), refused with a reason. */
    public function delete(Employee $actor, Assignment $assignment): bool
    {
        return $this->update($actor, $assignment);
    }

    /** Managing Pay means seeing it. */
    public function viewPay(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewPay->value, $employee)
            || $this->managePay($actor, $employee);
    }

    public function managePay(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManagePay->value, $employee);
    }

    public function managePayOn(Employee $actor, Assignment $assignment): bool
    {
        return $this->managePay($actor, $assignment->employee);
    }
}
