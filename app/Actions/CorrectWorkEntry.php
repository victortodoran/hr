<?php

namespace App\Actions;

use App\Enums\WorkEntryState;
use App\Models\WorkEntry;

/**
 * Corrects a Work Entry: its Assignment, date, hours or description.
 */
class CorrectWorkEntry
{
    /**
     * Correct the Work Entry and return whether it changed. A Declined one
     * goes back to Pending, without who declined it or why. The write only
     * changes one still Pending or Declined, so an approval made since the
     * policy check is never undone; then nothing changes and false is
     * returned.
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
