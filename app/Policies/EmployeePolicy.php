<?php

namespace App\Policies;

use App\Enums\DirectoryTab;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Employee;

/**
 * Each check delegates to the Gate of its Permission, so Roles stay the
 * single source of access.
 */
class EmployeePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->holds(Permission::ViewEmployeeRecords);
    }

    /**
     * Every Employee may open their own page whatever their Roles, as it always
     * shows their Reporting Chain.
     */
    public function view(Employee $actor, Employee $employee): bool
    {
        return $actor->is($employee)
            || $actor->reachesThrough(Permission::seeingEmployeePages(), $employee);
    }

    /**
     * Not named viewTimeOff, since the Gate would then send the Permission's own
     * check back here.
     */
    public function viewTimeOffAndPaidDays(Employee $actor, Employee $employee): bool
    {
        return $actor->reachesThrough(Permission::seeingTimeOff(), $employee);
    }

    /**
     * Not named managePaidDays, since the Gate would then send the Permission's
     * own check back here.
     */
    public function setPaidDays(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManagePaidDays->value, $employee);
    }

    public function viewTeam(Employee $actor): bool
    {
        return $actor->hasDirectReports();
    }

    public function viewDirectory(Employee $actor): bool
    {
        return DirectoryTab::openableBy($actor) !== [];
    }

    public function create(Employee $actor): bool
    {
        return $actor->can(Permission::AddEmployees->value);
    }

    public function update(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageEmployees->value, $employee);
    }

    public function resendInvitation(Employee $actor, Employee $employee): bool
    {
        return $this->update($actor, $employee)
            && $employee->hasPendingInvitation()
            && $employee->employmentStatus() !== EmploymentStatus::Former;
    }
}
