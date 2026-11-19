<?php

namespace App\Actions;

use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

/**
 * Changes an Assignment's Job Title, dates or Capacity and, when its end
 * moves earlier, removes the Pay changes dated after the new end.
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
        });
    }
}
