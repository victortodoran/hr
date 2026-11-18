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
     * Save the assessment as given: a Goal sent with no Outcome has its
     * Outcome cleared, and a Goal not sent keeps the one it had.
     *
     * @param  array<int, Outcome|null>  $goalOutcomes  Each Goal's Outcome, by Goal ID
     */
    public function save(PerformanceEvaluation $evaluation, array $goalOutcomes, ?Outcome $outcome, ?string $summary): void
    {
        DB::transaction(function () use ($evaluation, $goalOutcomes, $outcome, $summary): void {
            $this->write($evaluation, $goalOutcomes, $outcome, $summary);
        });
    }

    /**
     * Save the assessment and Complete the Performance Evaluation, or
     * refuse, saving nothing, while it has no Goals or still lacks an
     * Outcome somewhere or its summary.
     *
     * @param  array<int, Outcome|null>  $goalOutcomes  Each Goal's Outcome, by Goal ID
     *
     * @throws ValidationException
     */
    public function complete(PerformanceEvaluation $evaluation, array $goalOutcomes, ?Outcome $outcome, ?string $summary): void
    {
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

        DB::transaction(function () use ($evaluation, $goalOutcomes, $outcome, $summary): void {
            $this->write($evaluation, $goalOutcomes, $outcome, $summary);
            $evaluation->forceFill(['state' => PerformanceEvaluationState::Completed, 'completed_at' => now()])->save();
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
