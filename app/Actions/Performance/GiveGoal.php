<?php

namespace App\Actions\Performance;

use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

class GiveGoal
{
    /**
     * The Evaluation stays locked until the Goal is given, so a Complete running
     * at the same time waits and then sees it.
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
