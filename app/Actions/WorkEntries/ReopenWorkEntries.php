<?php

namespace App\Actions\WorkEntries;

use App\Enums\Permission;
use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\WorkEntry;

class ReopenWorkEntries
{
    /** @param  array<int, int>  $ids */
    public function reopen(Employee $actor, array $ids): int
    {
        return WorkEntry::query()
            ->ofEmployees(Employee::query()->reopenableBy($actor, Permission::ReopenWorkEntries))
            ->whereKey($ids)
            ->approved()
            ->update(['state' => WorkEntryState::Pending, 'approved_by' => null, 'approved_at' => null]);
    }
}
