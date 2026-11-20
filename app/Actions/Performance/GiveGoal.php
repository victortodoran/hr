<?php

namespace App\Actions\Performance;

use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

/**
 * Gives a Performance Evaluation a Goal, only while it is Open.
 */
class GiveGoal
{
    /**
     * Give the Goal and return true, or return false, giving nothing,
     * when the Performance Evaluation was Completed since the policy
     * check. The Evaluation stays locked until the Goal is given, so a
     * Complete running at the same time waits and then sees it.
     *
     * @param  array{title: string, description: string}  $goal
     */
    public function give(PerformanceEvaluation $evaluation, array $goal): bool
    {
        return DB::transaction(function () use ($evaluation, $goal): bool {
            if (! PerformanceEvaluation::query()->whereKey($evaluation->id)->open()->lockForUpdate()->exists()) {
                return false;
            }

            $evaluation->goals()->create($goal);

            return true;
        });
    }
}
