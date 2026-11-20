<?php

namespace App\Actions\Performance;

use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

/**
 * Removes a Goal, with its Progress history and Comments, only while its
 * Performance Evaluation is Open.
 */
class RemoveGoal
{
    /**
     * Remove the Goal and return true, or return false, removing nothing,
     * when its Performance Evaluation was Completed since the policy
     * check.
     */
    public function remove(Goal $goal): bool
    {
        return DB::transaction(function () use ($goal): bool {
            if (! PerformanceEvaluation::query()->whereKey($goal->performance_evaluation_id)->open()->lockForUpdate()->exists()) {
                return false;
            }

            $goal->delete();

            return true;
        });
    }
}
