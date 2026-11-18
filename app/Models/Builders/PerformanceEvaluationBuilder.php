<?php

namespace App\Models\Builders;

use App\Enums\PerformanceEvaluationState;
use App\Enums\Progress;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Performance Evaluations.
 *
 * @extends ModelBuilder<PerformanceEvaluation>
 */
class PerformanceEvaluationBuilder extends ModelBuilder
{
    /**
     * Limit the query to Open Performance Evaluations, which the Employee's
     * Manager has yet to complete.
     */
    public function open(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), PerformanceEvaluationState::Open);
        });
    }

    /**
     * Load how many of each one's Goals are At Risk now, as
     * at_risk_goals_count.
     */
    public function withAtRiskGoalsCount(): static
    {
        return $this->withCount(['goals as at_risk_goals_count' => fn (Builder $goals) => $goals->where('progress', Progress::AtRisk)]);
    }
}
