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
 * The yearly look at how one Employee did over one calendar year, holding
 * the Goals their Manager gave them for it. An Employee has at most one per
 * year. It belongs to the Employee, not to a Manager, so a new Manager takes
 * over the same one (ADR 0008).
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

    /**
     * Every Performance Evaluation starts Open.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'state' => 'open', // @pest-mutate-ignore (the column defaults to open too, so stored Performance Evaluations look the same)
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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

    /**
     * Whether its Manager has Completed it.
     */
    public function isCompleted(): bool
    {
        return $this->state === PerformanceEvaluationState::Completed;
    }

    /**
     * The Outcome of the whole as the lists show it: only once Completed.
     */
    public function completedOutcome(): ?Outcome
    {
        return $this->isCompleted() ? $this->outcome : null;
    }

    /**
     * How many of its Goals are At Risk as the lists show it: only while
     * Open, from at_risk_goals_count.
     */
    public function openAtRiskGoalsCount(): int
    {
        return $this->isCompleted() ? 0 : (int) $this->at_risk_goals_count;
    }

    /**
     * The Goals the Employee's Manager gave for it, in the order given.
     *
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class)->orderBy('id');
    }
}
