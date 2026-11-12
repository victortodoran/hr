<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\WorkEntry;

/**
 * Who may see and change Work Entries. Each check is made against the
 * Employee the Work Entries belong to, through the Gate of the Permission
 * behind it, so the acting Employee's Scope decides.
 */
class WorkEntryPolicy
{
    /**
     * Seeing an Employee's Work Entries needs view work entries for them, or
     * manage work entries, since managing them means seeing them.
     */
    public function viewAny(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ViewWorkEntries->value, $employee)
            || $actor->can(Permission::ManageWorkEntries->value, $employee);
    }

    /**
     * Recording hours for an Employee needs manage work entries for them.
     * Nobody records hours for the top Employee: with no Manager, nobody
     * could approve them.
     */
    public function create(Employee $actor, Employee $employee): bool
    {
        return ! $employee->is_top
            && $actor->can(Permission::ManageWorkEntries->value, $employee);
    }

    /**
     * Changing a Work Entry needs what recording it does, and only a Pending
     * one can be changed: an Approved one must be reopened first, and a
     * Locked one never can be.
     */
    public function update(Employee $actor, WorkEntry $workEntry): bool
    {
        return $workEntry->isPending() && $this->create($actor, $workEntry->employee);
    }

    /**
     * Deleting a Work Entry needs manage work entries for the Employee whose
     * Assignment it is on, and only a Pending one can be deleted. Unlike
     * changing, it is allowed for the top Employee, so Pending Work Entries
     * nobody can approve can be cleared away.
     */
    public function delete(Employee $actor, WorkEntry $workEntry): bool
    {
        return $workEntry->isPending()
            && $actor->can(Permission::ManageWorkEntries->value, $workEntry->employee);
    }

    /**
     * The Approvals page is for those who approve, reopen or lock Work
     * Entries: anyone who is someone's effective Manager, or whose Role
     * grants reopen or lock.
     */
    public function viewApprovals(Employee $actor): bool
    {
        return $actor->managesAnyone()
            || $actor->holds(Permission::ReopenWorkEntries)
            || $this->lock($actor);
    }

    /**
     * Locking Work Entries needs lock work entries, which applies
     * company-wide, the locker's own Work Entries included.
     */
    public function lock(Employee $actor): bool
    {
        return $actor->holds(Permission::LockWorkEntries);
    }
}
