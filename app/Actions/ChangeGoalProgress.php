<?php

namespace App\Actions;

use App\Enums\Progress;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\ProgressChange;
use Illuminate\Support\Facades\DB;

/**
 * Changes a Goal's Progress and keeps the change, with who made it and
 * the Progress before, in one transaction.
 */
class ChangeGoalProgress
{
    /**
     * Change the Goal's Progress and return the change kept, or null when
     * the Goal already has that Progress, which keeps nothing.
     */
    public function change(Goal $goal, Progress $progress, Employee $changedBy): ?ProgressChange
    {
        return DB::transaction(function () use ($goal, $progress, $changedBy): ?ProgressChange {
            $previous = Goal::query()->lockForUpdate()->findOrFail($goal->id)->progress;

            if ($previous === $progress) {
                return null;
            }

            $change = $goal->progressChanges()->create([
                'changed_by_id' => $changedBy->id,
                'previous_progress' => $previous,
                'progress' => $progress,
            ]);
            $goal->forceFill(['progress' => $progress])->save();

            return $change;
        });
    }
}
