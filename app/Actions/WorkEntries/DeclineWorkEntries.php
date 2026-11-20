<?php

namespace App\Actions\WorkEntries;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\WorkEntry;

class DeclineWorkEntries
{
    /** @param  array<int, int>  $ids */
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
