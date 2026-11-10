<?php

namespace App\Policies;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Employee;

/**
 * Who may add and change Employees. Each check delegates to the Gate of the
 * Permission behind it, so Roles stay the single source of access.
 */
class EmployeePolicy
{
    /**
     * Adding an Employee needs manage employees.
     */
    public function create(Employee $actor): bool
    {
        return $actor->can(Permission::ManageEmployees->value);
    }

    /**
     * Editing any Employee's record needs manage employees.
     */
    public function update(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageEmployees->value);
    }

    /**
     * An invitation can be resent only while the Employee has yet to set a
     * password, and never once they have left.
     */
    public function resendInvitation(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageEmployees->value)
            && $employee->hasPendingInvitation()
            && $employee->employmentStatus() !== EmploymentStatus::Former;
    }

    /**
     * Naming the top Employee needs manage company, and only a current
     * Employee who isn't already top and has no Leaving Date can take the
     * spot, since the top Employee can't leave without a successor.
     */
    public function becomeTop(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageCompany->value)
            && ! $employee->is_top
            && $employee->leaving_date === null
            && $employee->employmentStatus() === EmploymentStatus::Current;
    }
}
