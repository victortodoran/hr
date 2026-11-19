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
     * then holds only the Employees those Scopes cover.
     */
    public function viewAny(Employee $actor): bool
    {
        return $actor->holds(Permission::ViewEmployeeRecords);
    }

    /**
     * Every Employee may always open their own page, Profile, whatever their
     * Roles, as it always shows their Reporting Chain. Opening anyone else's
     * needs something on it the viewer may see: their record, their
     * Assignments (which seeing their Pay also allows), or their Time Off
     * and Paid Days.
     */
    public function view(Employee $actor, Employee $employee): bool
    {
        return $actor->is($employee)
            || $actor->can(Permission::ViewEmployeeRecords->value, $employee)
            || $actor->can('viewAny', [Assignment::class, $employee])
            || $this->viewTimeOffAndPaidDays($actor, $employee);
    }

    /**
     * Seeing an Employee's Time Off and Paid Days needs one of the
     * Permissions for seeing Time Off (Permission::seeingTimeOff()) to reach
     * them: view time off, or manage paid days, since setting their Paid
     * Days means seeing them. It isn't named viewTimeOff, since the Gate
     * would then send the Permission's own check back here.
     */
    public function viewTimeOffAndPaidDays(Employee $actor, Employee $employee): bool
    {
        return $actor->reachesThrough(Permission::seeingTimeOff(), $employee);
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
     * Adding an Employee needs add employees, which applies company-wide.
     */
    public function create(Employee $actor): bool
    {
        return $actor->can(Permission::AddEmployees->value);
    }

    /**
     * Editing an Employee's record needs manage employees with a Scope that
     * covers them.
     */
    public function update(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageEmployees->value, $employee);
    }

    /**
     * Resending an invitation needs what editing the Employee's record
     * needs, and works only while they have yet to set a password, and
     * never once they have left.
     */
    public function resendInvitation(Employee $actor, Employee $employee): bool
    {
        return $this->update($actor, $employee)
            && $employee->hasPendingInvitation()
            && $employee->employmentStatus() !== EmploymentStatus::Former;
    }
}
