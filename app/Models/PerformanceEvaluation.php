<?php

namespace App\Models;

use App\Enums\PerformanceEvaluationState;
use Database\Factories\PerformanceEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Collection<int, Goal> $goals
 * @property-read int|null $goals_count
 */
#[Fillable(['year'])] // @pest-mutate-ignore (class attributes never show as covered)
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
     * The Goals the Employee's Manager gave for it, in the order given.
     *
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class)->orderBy('id');
    }
}
