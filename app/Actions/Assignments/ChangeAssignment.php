<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

/**
 * Changes an Assignment's Job Title, dates or Capacity and keeps its Pay
 * inside it: when its end moves earlier, removes the Pay changes dated
 * after the new end; when its start moves later, moves the Pay change in
 * force on the new start to it and removes the older ones.
 */
class ChangeAssignment
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function change(Assignment $assignment, array $changes): void
    {
        DB::transaction(function () use ($assignment, $changes): void {
            $assignment->update($changes);
            $assignment->dropPayAfterEnd();
            $assignment->movePayToStart();
        });
    }
}
