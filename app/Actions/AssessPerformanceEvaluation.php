<?php

namespace App\Actions;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\Goal;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the Manager's Outcome for one of a Performance Evaluation's Goals,
 * or its overall Outcome and its summary, and, when asked, Completes it,
 * or Reopens it once Completed.
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
     * Save the Outcome of the whole and the summary as given, either of
     * them empty, and return true. Return false, saving nothing, when the
     * Performance Evaluation was Completed since the policy check.
     */
    public function save(PerformanceEvaluation $evaluation, ?Outcome $outcome, ?string $summary): bool
    {
        return DB::transaction(function () use ($evaluation, $outcome, $summary): bool {
            $locked = $this->lockWhileOpen($evaluation->id);

            if ($locked === null) {
                return false;
            }

            $this->write($locked, $outcome, $summary);

            return true;
        });
    }

    /**
     * Save the Outcome of the whole and the summary, Complete the
     * Performance Evaluation and return true, or refuse, saving nothing,
     * while it has no Goals, a Goal still lacks an Outcome, or the whole
     * lacks its Outcome or summary. Return false, saving nothing, when it
     * was Completed since the policy check. Its Goals are read while it is
     * locked, so a Goal or Goal Outcome given at the same time is either
     * seen here or refused.
     *
     * @throws ValidationException
     */
    public function complete(PerformanceEvaluation $evaluation, ?Outcome $outcome, ?string $summary): bool
    {
        return DB::transaction(function () use ($evaluation, $outcome, $summary): bool {
            $locked = $this->lockWhileOpen($evaluation->id);

            if ($locked === null) {
                return false;
            }

            if (! $locked->goals()->exists()) {
                throw ValidationException::withMessages(['outcome' => __('Give at least one Goal before completing.')]);
            }

            $isReady = ! $locked->goals()->whereNull('outcome')->exists()
                && $outcome !== null
                && filled($summary);

            if (! $isReady) {
                throw ValidationException::withMessages(['outcome' => __('Give every Goal and the whole an Outcome, and write a summary, before completing.')]);
            }

            $this->write($locked, $outcome, $summary);
            $locked->forceFill(['state' => PerformanceEvaluationState::Completed, 'completed_at' => now()])->save();

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

    private function write(PerformanceEvaluation $evaluation, ?Outcome $outcome, ?string $summary): void
    {
        $evaluation->forceFill(['outcome' => $outcome, 'summary' => $summary])->save();
    }
}
