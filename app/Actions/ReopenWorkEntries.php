<?php

namespace App\Actions;

use App\Enums\Permission;
use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\WorkEntry;

/**
 * Reopens selected Work Entries from the Work Entries page: sends Approved
 * ones back to Pending so they can be corrected. Only a Role that grants
 * reopening reopens them; being the Manager is not enough, and nobody
 * reopens their own.
 */
class ReopenWorkEntries
{
    /**
     * Reopen the selected Work Entries the actor may reopen: Approved ones of
     * Employees their reopen Scope covers, never their own, sent back to
     * Pending with their approval withdrawn. The rest are skipped. Returns
     * how many were reopened.
     *
     * @param  array<int, int>  $ids
     */
    public function reopen(Employee $actor, array $ids): int
    {
        return WorkEntry::query()
            ->ofEmployees(Employee::query()->reopenableBy($actor, Permission::ReopenWorkEntries))
            ->whereKey($ids)
            ->approved()
            ->update(['state' => WorkEntryState::Pending, 'approved_by' => null, 'approved_at' => null]);
    }
}
