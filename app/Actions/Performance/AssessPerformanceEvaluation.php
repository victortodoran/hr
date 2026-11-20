<?php

namespace App\Actions\Performance;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessPerformanceEvaluation
{
    /**
     * The Evaluation is locked first, so a Complete either sees the Outcome or
     * the Outcome is refused, and the Goal is written as a query, so the Outcome is saved
     * even when it matches a stale loaded one.
     */
    public function giveGoalOutcome(Goal $goal, ?Outcome $outcome): bool
    {
        return DB::transaction(function () use ($goal, $outcome): bool {
            if ($this->lockWhileOpen($goal->performance_evaluation_id) === null) {
                return false;
            }

            Goal::query()->whereKey($goal->id)->update(['outcome' => $outcome]);

            return true;
        });
    }

    /** The Evaluation is locked first, so a Complete either sees the summary or the summary is refused. */
    public function writeSummary(PerformanceEvaluation $evaluation, ?string $summary): bool
    {
        return DB::transaction(function () use ($evaluation, $summary): bool {
            $locked = $this->lockWhileOpen($evaluation->id);

            if ($locked === null) {
                return false;
            }

            $locked->forceFill(['summary' => $summary])->save();

            return true;
        });
    }

    /**
     * Goals and summary are read under the lock, so one saved at the same time
     * is either seen here or refused.
     *
     * @throws ValidationException
     */
    public function complete(PerformanceEvaluation $evaluation, Outcome $outcome): bool
    {
        return DB::transaction(function () use ($evaluation, $outcome): bool {
            $locked = $this->lockWhileOpen($evaluation->id);

            if ($locked === null) {
                return false;
            }

            if (! $locked->goals()->exists()) {
                throw ValidationException::withMessages(['outcome' => __('Give at least one Goal before completing.')]);
            }

            if ($locked->goals()->whereNull('outcome')->exists() || blank($locked->summary)) {
                throw ValidationException::withMessages(['outcome' => __('Give every Goal an Outcome and write the summary before completing.')]);
            }

            $locked->forceFill(['outcome' => $outcome, 'state' => PerformanceEvaluationState::Completed, 'completed_at' => now()])->save();

            return true;
        });
    }

    public function reopen(PerformanceEvaluation $evaluation): void
    {
        $evaluation->forceFill(['state' => PerformanceEvaluationState::Open, 'completed_at' => null])->save();
    }

    /**
     * Every write that needs it Open takes this lock first, so a Complete never
     * interleaves with one; writing through the row returned saves every value
     * even when it matches a stale loaded one.
     */
    private function lockWhileOpen(int $evaluationId): ?PerformanceEvaluation
    {
        return PerformanceEvaluation::query()->whereKey($evaluationId)->open()->lockForUpdate()->first();
    }
}
