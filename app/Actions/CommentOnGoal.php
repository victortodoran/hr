<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

/**
 * Writes a Comment on a Goal, only while its Performance Evaluation is
 * Open.
 */
class CommentOnGoal
{
    /**
     * Write the Comment and return true, or return false, writing nothing,
     * when the Goal's Performance Evaluation was Completed since the
     * policy check.
     */
    public function comment(Goal $goal, Employee $author, string $body): bool
    {
        return DB::transaction(function () use ($goal, $author, $body): bool {
            if (! PerformanceEvaluation::query()->whereKey($goal->performance_evaluation_id)->open()->lockForUpdate()->exists()) {
                return false;
            }

            $goal->comments()->create(['author_id' => $author->id, 'body' => $body]);

            return true;
        });
    }
}
