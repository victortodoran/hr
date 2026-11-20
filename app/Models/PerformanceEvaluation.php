<?php

namespace App\Models;

use App\Enums\Outcome;
use App\Enums\PerformanceEvaluationState;
use App\Models\Builders\PerformanceEvaluationBuilder;
use Database\Factories\PerformanceEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * It belongs to the Employee, not to a Manager, so a new Manager takes over the same one (ADR 0008).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $year
 * @property PerformanceEvaluationState $state
 * @property Outcome|null $outcome
 * @property string|null $summary
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Collection<int, Goal> $goals
 * @property-read int|null $goals_count
 * @property-read int|null $at_risk_goals_count
 */
#[Fillable(['year'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(PerformanceEvaluationBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class PerformanceEvaluation extends Model
{
    /** @use HasFactory<PerformanceEvaluationFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = [
        'state' => 'open', // @pest-mutate-ignore (the column defaults to open too, so stored Performance Evaluations look the same)
    ];

    /** @return array{int, int} */
    public static function creatableYears(): array
    {
        return [today()->year, today()->year + 1];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'state' => PerformanceEvaluationState::class,
            'outcome' => Outcome::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isCompleted(): bool
    {
        return $this->state === PerformanceEvaluationState::Completed;
    }

    public function completedOutcome(): ?Outcome
    {
        return $this->isCompleted() ? $this->outcome : null;
    }

    /** Needs at_risk_goals_count loaded. */
    public function openAtRiskGoalsCount(): int
    {
        return $this->isCompleted() ? 0 : (int) $this->at_risk_goals_count;
    }

    /**
     * Needs goals_count and at_risk_goals_count loaded.
     *
     * @return array{id: int, year: int, state: string, goals_count: int, at_risk_goals_count: int, outcome: string|null}
     */
    public function listed(): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'state' => $this->state->value,
            'goals_count' => (int) $this->goals_count,
            'at_risk_goals_count' => $this->openAtRiskGoalsCount(),
            'outcome' => $this->completedOutcome()?->value,
        ];
    }

    /** @return HasMany<Goal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class)->orderBy('id');
    }
}
