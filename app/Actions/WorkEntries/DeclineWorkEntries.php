<?php

namespace App\Actions\WorkEntries;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\WorkEntry;

/**
 * Declines selected Work Entries from the Approvals page, several at once
 * with one reason. Nobody declines their own Work Entries.
 */
class DeclineWorkEntries
{
    /**
     * Decline the selected Work Entries the actor may decline, the same ones
     * they may approve: Pending ones of Employees whose effective Manager
     * they are. Each records who declined it, when and why. The rest are
     * skipped. Returns how many were declined.
     *
     * @param  array<int, int>  $ids
     */
    public function decline(Employee $actor, array $ids, string $reason): int
    {
        return WorkEntry::query()
            ->decidableBy($actor)
            ->whereKey($ids)
            ->update([
                'state' => WorkEntryState::Declined,
                'declined_by' => $actor->id,
                'declined_at' => now(),
                'decline_reason' => $reason,
            ]);
    }
}
