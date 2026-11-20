<?php

namespace App\Actions\Performance;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the Manager's Outcome for one of a Performance Evaluation's Goals
 * or its summary while it is Open, Completes it by giving the whole its
 * Outcome, or Reopens it once Completed.
 */
class AssessPerformanceEvaluation
{
    /**
     * Give the Goal the Outcome, or clear it with null, and return true.
     * Return false, saving nothing, when its Performance Evaluation was
     * Completed since the policy check. The Evaluation is locked first, so
     * a Complete either sees the Outcome or refuses it, and the Goal is
     * written as a query, so the Outcome sent is saved even when it matches
     * the one loaded before a save from another tab.
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

    /**
     * Save the summary as given, or clear it with null, and return true.
     * Return false, saving nothing, when the Performance Evaluation was
     * Completed since the policy check. The Evaluation is locked first, so
     * a Complete either sees the summary or refuses it.
     */
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
     * Give the whole its Outcome, Complete the Performance Evaluation and
     * return true, or refuse, saving nothing, while it has no Goals, a Goal
     * still lacks an Outcome or the summary is empty. Return false, saving
     * nothing, when it was Completed since the policy check. Its Goals and
     * summary are read while it is locked, so a Goal, Goal Outcome or
     * summary saved at the same time is either seen here or refused. The
     * whole's Outcome is only ever saved here, by Completing.
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

    /**
     * Send a Completed Performance Evaluation back to Open, keeping its
     * Goals, Outcomes and summary for its Manager to correct.
     */
    public function reopen(PerformanceEvaluation $evaluation): void
    {
        $evaluation->forceFill(['state' => PerformanceEvaluationState::Open, 'completed_at' => null])->save();
    }

    /**
     * Lock the Performance Evaluation until the transaction ends and
     * return it as it is now, or null when it is no longer Open. Every
     * write that needs it Open takes this lock first, so a Complete never
     * interleaves with one. Writing through the row returned, not the one
     * loaded earlier, saves every value sent even when it matches what
     * was loaded before another save.
     */
    private function lockWhileOpen(int $evaluationId): ?PerformanceEvaluation
    {
        return PerformanceEvaluation::query()->whereKey($evaluationId)->open()->lockForUpdate()->first();
    }
}
