<?php

namespace App\Actions\Performance;

use App\Enums\Progress;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use App\Models\ProgressChange;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ChangeGoalProgress
{
    /**
     * The Evaluation is locked before the Goal, in the same order Completing
     * locks them.
     *
     * @throws AuthorizationException
     */
    public function change(Goal $goal, Progress $progress, Employee $changedBy): ?ProgressChange
    {
        return DB::transaction(function () use ($goal, $progress, $changedBy): ?ProgressChange {
            if (! PerformanceEvaluation::query()->whereKey($goal->performance_evaluation_id)->open()->lockForUpdate()->exists()) {
                throw new AuthorizationException;
            }

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
