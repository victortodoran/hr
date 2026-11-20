<?php

namespace App\Actions\WorkEntries;

use App\Enums\WorkEntryState;
use App\Models\WorkEntry;

class CorrectWorkEntry
{
    /**
     * Only a Pending or Declined row changes, so an approval made since the
     * policy check wins.
     *
     * @param  array{assignment_id: int, date: string, minutes: int, description: string}  $changes
     */
    public function correct(WorkEntry $workEntry, array $changes): bool
    {
        $changed = WorkEntry::query()
            ->whereKey($workEntry->id)
            ->whereIn('state', [WorkEntryState::Pending, WorkEntryState::Declined])
            ->update([
                ...$changes,
                'state' => WorkEntryState::Pending,
                'declined_by' => null,
                'declined_at' => null,
                'decline_reason' => null,
                'updated_at' => now(),
            ]);

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Work Entry is approved between the policy check and this write)
            return false;
        }

        $workEntry->refresh();

        return true;
    }
}
