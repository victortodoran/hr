<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goal_id
 * @property int $author_id
 * @property string $body
 * @property Carbon $created_at
 * @property-read Goal $goal
 * @property-read Employee $author
 */
#[Fillable(['author_id', 'body'])] // @pest-mutate-ignore (class attributes never show as covered)
class Comment extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }
}
