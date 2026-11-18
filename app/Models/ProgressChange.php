<?php

namespace App\Models;

use App\Enums\Progress;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change to a Goal's Progress, kept for good with who made it, when,
 * and the Progress before. Never changed afterwards; it goes only with
 * its Goal.
 *
 * @property int $id
 * @property int $goal_id
 * @property int $changed_by_id
 * @property Progress $previous_progress
 * @property Progress $progress
 * @property Carbon $created_at
 * @property-read Goal $goal
 * @property-read Employee $changedBy
 */
#[Fillable(['changed_by_id', 'previous_progress', 'progress'])] // @pest-mutate-ignore (class attributes never show as covered)
class ProgressChange extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_progress' => Progress::class,
            'progress' => Progress::class,
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * The Employee or Manager who made the change.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'changed_by_id');
    }
}
