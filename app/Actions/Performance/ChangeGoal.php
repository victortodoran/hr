<?php

namespace App\Actions\Performance;

use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

/**
 * Changes a Goal's title and description, only while its Performance
 * Evaluation is Open.
 */
class ChangeGoal
{
    /**
     * Change the Goal and return true, or return false, changing nothing,
     * when its Performance Evaluation was Completed since the policy
     * check.
     *
     * @param  array{title: string, description: string}  $changes
     */
    public function change(Goal $goal, array $changes): bool
    {
        return DB::transaction(function () use ($goal, $changes): bool {
            if (! PerformanceEvaluation::query()->whereKey($goal->performance_evaluation_id)->open()->lockForUpdate()->exists()) {
                return false;
            }

            $goal->update($changes);

            return true;
        });
    }
}
