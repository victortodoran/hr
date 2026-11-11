<?php

namespace App\Models;

use Database\Factories\WorkEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How long an Employee worked on one of their Assignments on one date, and
 * what they did. It always falls within the Assignment's dates and never
 * on a day still to come.
 *
 * @property int $id
 * @property int $assignment_id
 * @property Carbon $date
 * @property int $minutes Whole minutes, in quarter-hour steps
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assignment $assignment
 */
#[Fillable(['assignment_id', 'date', 'minutes', 'description'])] // @pest-mutate-ignore (class attributes never show as covered)
class WorkEntry extends Model
{
    /** @use HasFactory<WorkEntryFactory> */
    use HasFactory;

    /**
     * The most an Employee can work on one date, across all their
     * Assignments.
     */
    public const int MINUTES_IN_A_DAY = 24 * 60; // @pest-mutate-ignore (constants are evaluated before coverage starts; the 24-hour tests pin the value)

    /**
     * Minutes as hours people read, such as "7.5" or "23".
     */
    public static function formatHours(int $minutes): string
    {
        $hours = number_format($minutes / 60, 2, '.', '');

        return rtrim(rtrim($hours, '0'), '.');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
