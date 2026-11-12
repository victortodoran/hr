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
            || $this->create($actor, $employee);
    }

    /**
     * Recording hours for an Employee needs manage work entries for them.
     */
    public function create(Employee $actor, Employee $employee): bool
    {
        return $actor->can(Permission::ManageWorkEntries->value, $employee);
    }

    /**
     * Changing or deleting a Work Entry needs manage work entries for the
     * Employee whose Assignment it is on, and only a Pending one can be
     * changed: an Approved one must be reopened first, and a Locked one never
     * can be.
     */
    public function update(Employee $actor, WorkEntry $workEntry): bool
    {
        return $workEntry->isPending() && $this->create($actor, $workEntry->employee);
    }

    /**
     * The Approvals page is for those who approve or reopen Work Entries.
     */
    public function viewApprovals(Employee $actor): bool
    {
        return $actor->holds(Permission::ApproveWorkEntries) || $actor->holds(Permission::ReopenWorkEntries);
    }
}
