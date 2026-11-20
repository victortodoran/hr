<?php

namespace App\Models;

use App\Enums\TravelRequestState;
use App\Models\Builders\TravelRequestBuilder;
use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $assignment_id
 * @property string $destination
 * @property Carbon $first_date
 * @property Carbon $last_date
 * @property string $purpose
 * @property TravelRequestState $state
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decline_reason
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Assignment $assignment
 * @property-read Employee|null $decider
 * @property-read Collection<int, TravelExpense> $travelExpenses
 */
#[Fillable(['assignment_id', 'destination', 'first_date', 'last_date', 'purpose'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(TravelRequestBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class TravelRequest extends Model
{
    /** @use HasFactory<TravelRequestFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Travel Requests look the same)
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'first_date' => 'date',
            'last_date' => 'date',
            'state' => TravelRequestState::class,
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Weekends count too, unlike Time Off. */
    public function days(): int
    {
        return (int) $this->first_date->diffInDays($this->last_date) + 1;
    }

    public function isCancellable(): bool
    {
        return in_array($this->state, TravelRequestState::standing(), true)
            && today()->isBefore($this->first_date);
    }

    /** A Cancelled one with a decision date was Approved first, and money already spent on it is still paid back (ADR 0007). */
    public function takesExpenses(): bool
    {
        return $this->state === TravelRequestState::Approved
            || ($this->state === TravelRequestState::Cancelled && $this->decided_at !== null);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Null for an Employee with no Manager, whose Travel Request is Approved with no one deciding.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }

    /** @return HasMany<TravelExpense, $this> */
    public function travelExpenses(): HasMany
    {
        return $this->hasMany(TravelExpense::class);
    }
}
