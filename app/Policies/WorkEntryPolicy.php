<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\WorkEntry;

class WorkEntryPolicy
{
    public function viewAny(Employee $actor, Employee $employee): bool
    {
        return $actor->reachesThrough(Permission::seeingWorkEntries(), $employee);
    }

    public function viewList(Employee $actor): bool
    {
        return $actor->seesOthersWorkEntries();
    }

    /** Nobody records hours for an Employee with no Manager: nobody could approve them. */
    public function create(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageWorkEntries->value, $employee)
            && ! $employee->hasNoManager();
    }

    public function update(Employee $actor, WorkEntry $workEntry): bool
    {
        return $workEntry->isOpen() && $this->create($actor, $workEntry->employee);
    }

    /**
     * Unlike changing, allowed for an Employee with no Manager, so Pending Work
     * Entries nobody can approve can be cleared away.
     */
    public function delete(Employee $actor, WorkEntry $workEntry): bool
    {
        return $workEntry->isOpen()
            && $actor->can(Permission::ManageWorkEntries->value, $workEntry->employee);
    }

    public function viewApprovals(Employee $actor): bool
    {
        return $actor->managesAnyone();
    }

    public function reopen(Employee $actor): bool
    {
        return $actor->holds(Permission::ReopenWorkEntries);
    }
}
