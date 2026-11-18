<?php

namespace App\Actions;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\PerformanceEvaluation;
use Illuminate\Support\Facades\DB;

/**
 * Saves the Manager's Outcomes for a Performance Evaluation's Goals, its
 * overall Outcome and its summary, all in one transaction, and, when
 * asked, Completes it.
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
     * Save the assessment and Complete the Performance Evaluation, unless
     * it still lacks Goals or an Outcome somewhere or its summary, in
     * which case nothing is saved and false is returned.
     *
     * @param  array<int, Outcome|null>  $goalOutcomes  Each Goal's Outcome, by Goal ID
     */
    public function complete(PerformanceEvaluation $evaluation, array $goalOutcomes, ?Outcome $outcome, ?string $summary): bool
    {
        $goalIds = $evaluation->goals()->pluck('id');
        $isReady = $goalIds->isNotEmpty()
            && $goalIds->every(fn (int $id): bool => ($goalOutcomes[$id] ?? null) !== null)
            && $outcome !== null
            && filled($summary);

        if (! $isReady) {
            return false;
        }

        DB::transaction(function () use ($evaluation, $goalOutcomes, $outcome, $summary): void {
            $this->write($evaluation, $goalOutcomes, $outcome, $summary);
            $evaluation->forceFill(['state' => PerformanceEvaluationState::Completed, 'completed_at' => now()])->save();
        });

        return true;
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
