<?php

namespace App\Actions\Performance;

use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

class CommentOnGoal
{
    /** Writes nothing when the Evaluation was Completed since the policy check. */
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
