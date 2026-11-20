<?php

namespace App\Models;

use App\Enums\WorkEntryState;
use App\Models\Builders\WorkEntryBuilder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\WorkEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assignment_id
 * @property Carbon $date
 * @property int $minutes Whole minutes, in quarter-hour steps
 * @property string $description
 * @property WorkEntryState $state
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $declined_by
 * @property Carbon|null $declined_at
 * @property string|null $decline_reason
 * @property-read float|int $hours The minutes as hours, such as 7.5
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assignment $assignment
 * @property-read Employee $employee
 * @property-read Employee|null $approver
 * @property-read Employee|null $decliner
 */
#[Fillable(['assignment_id', 'date', 'minutes', 'description'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(WorkEntryBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class WorkEntry extends Model
{
    /** @use HasFactory<WorkEntryFactory> */
    use HasFactory;

    /** Counted across all their Assignments on one date. */
    public const int MINUTES_IN_A_DAY = 24 * 60; // @pest-mutate-ignore (constants are evaluated before coverage starts; the 24-hour tests pin the value)

    public const int COPY_LOOKBACK_DAYS = 7; // @pest-mutate-ignore (constants are evaluated before coverage starts; the look-back tests pin the value)

    public static function copyLookbackFrom(CarbonImmutable $day): CarbonImmutable
    {
        return $day->subDays(self::COPY_LOOKBACK_DAYS);
    }

    /** @var array<string, string> */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Work Entries look the same)
    ];

    public static function formatHours(int $minutes): string
    {
        $hours = number_format($minutes / 60, 2, '.', '');

        return rtrim(rtrim($hours, '0'), '.');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'state' => WorkEntryState::class,
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return HasOneThrough<Employee, Assignment, $this> */
    public function employee(): HasOneThrough
    {
        return $this->hasOneThrough(Employee::class, Assignment::class, 'id', 'id', 'assignment_id', 'employee_id');
    }

    public function isPending(): bool
    {
        return $this->state === WorkEntryState::Pending;
    }

    public function isDeclined(): bool
    {
        return $this->state === WorkEntryState::Declined;
    }

    /** Declined counts, as changing it sends it back to Pending. */
    public function isOpen(): bool
    {
        return in_array($this->state, [WorkEntryState::Pending, WorkEntryState::Declined], true);
    }

    /**
     * Needs the decliner loaded.
     *
     * @return array{by: string, reason: string}|null
     */
    public function decline(): ?array
    {
        if ($this->decliner === null || $this->decline_reason === null) { // @pest-mutate-ignore (the work_entries_decline constraint keeps who and why both set or both empty)
            return null;
        }

        return ['by' => $this->decliner->name, 'reason' => $this->decline_reason];
    }

    /**
     * Needs the approver loaded.
     *
     * @return array{by: string, on: string}|null
     */
    public function approval(): ?array
    {
        return self::signOff($this->approver, $this->approved_at);
    }

    /**
     * @return array{by: string, on: string}|null
     */
    private static function signOff(?Employee $employee, ?CarbonInterface $at): ?array
    {
        if ($employee === null || $at === null) { // @pest-mutate-ignore (the work_entries_state constraint keeps who and when both set or both empty)
            return null;
        }

        return ['by' => $employee->name, 'on' => $at->toDateString()];
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function decliner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'declined_by');
    }

    /** @return Attribute<float|int, never> */
    protected function hours(): Attribute
    {
        return Attribute::get(fn (): float|int => $this->minutes / 60);
    }
}
