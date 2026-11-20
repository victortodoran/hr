<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

class ChangeAssignment
{
    /** @param  array<string, mixed>  $changes */
    public function change(Assignment $assignment, array $changes): void
    {
        DB::transaction(function () use ($assignment, $changes): void {
            $assignment->update($changes);
            $assignment->dropPayAfterEnd();
            $assignment->movePayToStart();
        });
    }
}
