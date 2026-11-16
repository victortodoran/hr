<?php

namespace App\Models;

use App\Enums\TravelRequestState;
use App\Models\Builders\TravelRequestBuilder;
use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Employee asking to travel for work on one of their Assignments, with a
 * destination, a first and a last date and a purpose. Every day from the
 * first to the last date counts, weekends included. It is Pending until
 * approved or declined, and can be Cancelled before its first day.
 *
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
 */
#[Fillable(['assignment_id', 'destination', 'first_date', 'last_date', 'purpose'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(TravelRequestBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class TravelRequest extends Model
{
    /** @use HasFactory<TravelRequestFactory> */
    use HasFactory;

    /**
     * Every Travel Request starts Pending.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Travel Requests look the same)
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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

    /**
     * How many days it counts: every day from the first to the last date,
     * both included, weekends too.
     */
    public function days(): int
    {
        return (int) $this->first_date->diffInDays($this->last_date) + 1;
    }

    /**
     * Whether the Employee can still cancel it: it is Pending or Approved,
     * and its first date is still to come.
     */
    public function isCancellable(): bool
    {
        return in_array($this->state, TravelRequestState::standing(), true)
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
     * The Assignment the travel is for, which leads to its Project and
     * Client.
     *
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * The Manager who approved or declined it, once decided. The Travel
     * Request of an Employee with no Manager is Approved with no one
     * deciding.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
