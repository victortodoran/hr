<?php

namespace App\Models;

use App\Enums\TimeOffState;
use App\Models\Builders\TimeOffBuilder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Database\Factories\TimeOffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $first_date
 * @property Carbon $last_date
 * @property bool $is_paid
 * @property TimeOffState $state
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decline_reason
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Employee|null $decider
 */
#[Table('time_off')] // @pest-mutate-ignore (class attributes never show as covered)
#[Fillable(['first_date', 'last_date', 'is_paid'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(TimeOffBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class TimeOff extends Model
{
    /** @use HasFactory<TimeOffFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Time Off looks the same)
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'first_date' => 'date',
            'last_date' => 'date',
            'is_paid' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
            'state' => TimeOffState::class,
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Public holidays count like any other weekday. */
    public static function weekdaysBetween(CarbonInterface $first, CarbonInterface $last): int
    {
        $weekdays = 0;

        foreach (CarbonPeriod::create($first, $last) as $date) {
            if ($date->isWeekday()) {
                $weekdays++;
            }
        }

        return $weekdays;
    }

    public function weekdays(): int
    {
        return self::weekdaysBetween($this->first_date, $this->last_date);
    }

    public function weekdaysIn(int $year): int
    {
        $first = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay()->max($this->first_date);
        $last = CarbonImmutable::createFromDate($year, 12, 31)->startOfDay()->min($this->last_date);

        return self::weekdaysBetween($first, $last);
    }

    /** @return array{id: int, first_date: string, last_date: string, days: int, is_paid: bool, state: string, decided_by: string|null, decline_reason: string|null} */
    public function details(): array
    {
        return [
            'id' => $this->id,
            'first_date' => $this->first_date->toDateString(),
            'last_date' => $this->last_date->toDateString(),
            'days' => $this->weekdays(),
            'is_paid' => $this->is_paid,
            'state' => $this->state->value,
            'decided_by' => $this->decider?->name,
            'decline_reason' => $this->decline_reason,
        ];
    }

    public function isCancellable(): bool
    {
        return in_array($this->state, TimeOffState::standing(), true)
            && today()->isBefore($this->first_date);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Null for the top Employee, whose Time Off is Approved with no one deciding.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
