<?php

namespace App\Models;

use App\Enums\Outcome;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something an Employee's Manager asks them to achieve during the year of
 * one Performance Evaluation, with a title and a description. It belongs
 * to no Assignment or Project.
 *
 * @property int $id
 * @property int $performance_evaluation_id
 * @property string $title
 * @property string $description
 * @property Outcome|null $outcome How well it was achieved, once the Manager has said
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PerformanceEvaluation $performanceEvaluation
 */
#[Fillable(['title', 'description'])] // @pest-mutate-ignore (class attributes never show as covered)
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => Outcome::class,
        ];
    }

    /**
     * @return BelongsTo<PerformanceEvaluation, $this>
     */
    public function performanceEvaluation(): BelongsTo
    {
        return $this->belongsTo(PerformanceEvaluation::class);
    }
}
