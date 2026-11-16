<?php

namespace App\Policies;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\Employee;

/**
 * Who may add and change Employees. Each check delegates to the Gate of the
 * Permission behind it, so Roles stay the single source of access.
 */
class EmployeePolicy
{
    /**
     * Listing Employees needs view employee records with any Scope; the list
     * then holds only the Employees that Scope covers.
     */
    public function viewAny(Employee $actor): bool
    {
        return $actor->widestScopeFor(Permission::ViewEmployeeRecords) !== null;
    }

    /**
     * Opening an Employee's page needs something on it the viewer may see:
     * their record, their Assignments (which seeing their Pay also
     * allows), or their Time Off and Paid Days.
     */
    public function view(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewEmployeeRecords->value, $employee)
            || $actor->can('viewAny', [Assignment::class, $employee])
            || $this->viewTimeOffAndPaidDays($actor, $employee);
    }

    /**
     * Seeing an Employee's Time Off and Paid Days needs view time off for
     * them, or manage paid days, since setting their Paid Days means
     * seeing them. It isn't named viewTimeOff, since the Gate would then
     * send the Permission's own check back here.
     */
    public function viewTimeOffAndPaidDays(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewTimeOff->value, $employee)
            || $this->setPaidDays($actor, $employee);
    }

    /**
     * Seeing and setting an Employee's Paid Days needs manage paid days for
     * them. It isn't named managePaidDays, since the Gate would then send
     * the Permission's own check back here.
     */
    public function setPaidDays(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManagePaidDays->value, $employee);
    }

    /**
     * The Team page is for Employees with Direct Reports. It needs no
     * Permission.
     */
    public function viewTeam(Employee $actor): bool
    {
        return $actor->hasDirectReports();
    }

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
}
