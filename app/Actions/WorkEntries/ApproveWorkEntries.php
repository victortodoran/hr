<?php

namespace App\Actions\WorkEntries;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\WorkEntry;

class ApproveWorkEntries
{
    /** @param  array<int, int>  $ids */
    public function approve(Employee $actor, array $ids): int
    {
        return WorkEntry::query()
            ->decidableBy($actor)
            ->whereKey($ids)
            ->update(['state' => WorkEntryState::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);
    }
}
