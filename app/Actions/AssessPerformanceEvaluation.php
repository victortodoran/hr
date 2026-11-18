<?php

namespace App\Actions;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the Manager's Outcomes for a Performance Evaluation's Goals, its
 * overall Outcome and its summary, all in one transaction, and, when
 * asked, Completes it, or Reopens it once Completed.
 */
class AssessPerformanceEvaluation
{
    /**
     * Save the assessment as given and return true: a Goal sent with no
     * Outcome has its Outcome cleared, and a Goal not sent keeps the one
     * it had. Return false, saving nothing, when the Performance
     * Evaluation was Completed since the policy check.
     *
     * @param  array<int, Outcome|null>  $goalOutcomes  Each Goal's Outcome, by Goal ID
     */
    public function save(PerformanceEvaluation $evaluation, array $goalOutcomes, ?Outcome $outcome, ?string $summary): bool
    {
        return DB::transaction(function () use ($evaluation, $goalOutcomes, $outcome, $summary): bool {
            if (! $this->lockWhileOpen($evaluation)) {
                return false;
            }

            $this->write($evaluation, $goalOutcomes, $outcome, $summary);

            return true;
        });
    }

    /**
     * Save the assessment, Complete the Performance Evaluation and return
     * true, or refuse, saving nothing, while it has no Goals or still
     * lacks an Outcome somewhere or its summary. Return false, saving
     * nothing, when it was Completed since the policy check. Its Goals are
     * read while it is locked, so a Goal given at the same time is either
     * seen here or refused.
     *
     * @param  array<int, Outcome|null>  $goalOutcomes  Each Goal's Outcome, by Goal ID
     *
     * @throws ValidationException
     */
    public function complete(PerformanceEvaluation $evaluation, array $goalOutcomes, ?Outcome $outcome, ?string $summary): bool
    {
        return DB::transaction(function () use ($evaluation, $goalOutcomes, $outcome, $summary): bool {
            if (! $this->lockWhileOpen($evaluation)) {
                return false;
            }

            $goalIds = $evaluation->goals()->pluck('id');

            if ($goalIds->isEmpty()) {
                throw ValidationException::withMessages(['outcome' => __('Give at least one Goal before completing.')]);
            }

            $isReady = $goalIds->every(fn (int $id): bool => ($goalOutcomes[$id] ?? null) !== null)
                && $outcome !== null
                && filled($summary);

            if (! $isReady) {
                throw ValidationException::withMessages(['outcome' => __('Give every Goal and the whole an Outcome, and write a summary, before completing.')]);
            }

            $this->write($evaluation, $goalOutcomes, $outcome, $summary);
            $evaluation->forceFill(['state' => PerformanceEvaluationState::Completed, 'completed_at' => now()])->save();

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
     * return whether it is still Open. Every write that needs it Open
     * takes this lock first, so a Complete never interleaves with one.
     */
    private function lockWhileOpen(PerformanceEvaluation $evaluation): bool
    {
        return PerformanceEvaluation::query()->whereKey($evaluation->id)->open()->lockForUpdate()->exists();
    }

    /**
     * @param  array<int, Outcome|null>  $goalOutcomes
     */
    private function write(PerformanceEvaluation $evaluation, array $goalOutcomes, ?Outcome $outcome, ?string $summary): void
    {
        foreach ($evaluation->goals()->get() as $goal) {
            if (array_key_exists($goal->id, $goalOutcomes)) {
                $goal->forceFill(['outcome' => $goalOutcomes[$goal->id]])->save();
            }
        }

        $evaluation->forceFill(['outcome' => $outcome, 'summary' => $summary])->save();
    }
}
