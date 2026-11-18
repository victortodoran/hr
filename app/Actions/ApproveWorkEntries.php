<?php

namespace App\Actions;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\WorkEntry;

/**
 * Approves selected Work Entries from the Approvals page, several at once.
 * Nobody approves their own Work Entries.
 */
class ApproveWorkEntries
{
    /**
     * Approve the selected Work Entries the actor may approve: Pending ones
     * of Employees whose effective Manager they are, recording who approved
     * them and when. The rest are skipped. Returns how many were approved.
     *
     * @param  array<int, int>  $ids
     */
    public function approve(Employee $actor, array $ids): int
    {
        return WorkEntry::query()
            ->decidableBy($actor)
            ->whereKey($ids)
            ->update(['state' => WorkEntryState::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);
    }
}
