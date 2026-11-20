<?php

namespace App\Models\Builders;

use App\Enums\PerformanceEvaluationState;
use App\Enums\Progress;
use App\Models\PerformanceEvaluation;
use Illuminate\Database\Eloquent\Builder;

/** @extends ModelBuilder<PerformanceEvaluation> */
class PerformanceEvaluationBuilder extends ModelBuilder
{
    public function open(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), PerformanceEvaluationState::Open);
        });
    }

    public function withAtRiskGoalsCount(): static
    {
        return $this->withCount(['goals as at_risk_goals_count' => fn (Builder $goals) => $goals->where('progress', Progress::AtRisk)]);
    }
}
