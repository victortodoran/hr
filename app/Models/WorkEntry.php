<?php

namespace App\Models;

use App\Enums\WorkEntryState;
use App\Support\Period;
use Carbon\CarbonInterface;
use Database\Factories\WorkEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope as QueryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

/**
 * How long an Employee worked on one of their Assignments on one date, and
 * what they did. It always falls within the Assignment's dates and never
 * on a day still to come. It starts Pending, can be Approved, and can then
 * be Locked for good; a Pending one can instead be Declined, with a reason.
 *
 * @property int $id
 * @property int $assignment_id
 * @property Carbon $date
 * @property int $minutes Whole minutes, in quarter-hour steps
 * @property string $description
 * @property WorkEntryState $state
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $locked_by
 * @property Carbon|null $locked_at
 * @property int|null $declined_by
 * @property Carbon|null $declined_at
 * @property string|null $decline_reason
 * @property-read float|int $hours The minutes as hours, such as 7.5
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assignment $assignment
 * @property-read Employee $employee
 * @property-read Employee|null $approver
 * @property-read Employee|null $locker
 * @property-read Employee|null $decliner
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
     * How many days before a day copying Work Entries onto it looks back
     * for the latest day with any.
     */
    public const int COPY_LOOKBACK_DAYS = 7; // @pest-mutate-ignore (constants are evaluated before coverage starts; the look-back tests pin the value)

    /**
     * Every Work Entry starts Pending.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'state' => 'pending', // @pest-mutate-ignore (the column defaults to pending too, so stored Work Entries look the same)
    ];

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
            'state' => WorkEntryState::class,
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
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

    /**
     * The Employee whose hours these are: the one on the Assignment.
     *
     * @return HasOneThrough<Employee, Assignment, $this>
     */
    public function employee(): HasOneThrough
    {
        return $this->hasOneThrough(Employee::class, Assignment::class, 'id', 'id', 'assignment_id', 'employee_id');
    }

    /**
     * Whether the Work Entry is Pending, the only state in which it can be
     * approved or declined.
     */
    public function isPending(): bool
    {
        return $this->state === WorkEntryState::Pending;
    }

    /**
     * Whether the Work Entry is Declined, so its hours count nowhere.
     */
    public function isDeclined(): bool
    {
        return $this->state === WorkEntryState::Declined;
    }

    /**
     * Whether the Work Entry can still be changed or deleted: while Pending,
     * or once Declined, when changing it sends it back to Pending.
     */
    public function isOpen(): bool
    {
        return in_array($this->state, [WorkEntryState::Pending, WorkEntryState::Declined], true);
    }

    /**
     * Who declined the Work Entry and why, or null unless it is Declined.
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
     * Who approved the Work Entry and on which day, or null while it is
     * Pending. Needs the approver loaded.
     *
     * @return array{by: string, on: string}|null
     */
    public function approval(): ?array
    {
        return self::signOff($this->approver, $this->approved_at);
    }

    /**
     * Who locked the Work Entry and on which day, or null until it is
     * Locked. Needs the locker loaded.
     *
     * @return array{by: string, on: string}|null
     */
    public function lock(): ?array
    {
        return self::signOff($this->locker, $this->locked_at);
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

    /**
     * The Employee who approved the Work Entry, while it is Approved or
     * Locked.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * The Employee who locked the Work Entry, once it is Locked.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    /**
     * The Employee who declined the Work Entry, while it is Declined.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function decliner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'declined_by');
    }

    /**
     * Limit the query to the Work Entries whose hours count: all but the
     * Declined ones.
     *
     * @param  Builder<WorkEntry>  $query
     */
    #[QueryScope]
    protected function counted(Builder $query): void
    {
        $query->where($query->qualifyColumn('state'), '!=', WorkEntryState::Declined);
    }

    /**
     * Limit the query to the Work Entries matching the filters, each applied
     * only when set: an Employee, a Project, a Period and a state.
     *
     * @param  Builder<WorkEntry>  $query
     * @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters
     */
    #[QueryScope]
    protected function filteredBy(Builder $query, array $filters): void
    {
        $query->when($filters['employee'] ?? null, fn (Builder $entries, int $employee) => $entries->whereRelation('assignment', 'employee_id', $employee))
            ->when($filters['project'] ?? null, fn (Builder $entries, int $project) => $entries->whereRelation('assignment', 'project_id', $project))
            ->when($filters['period'] ?? null, fn (Builder $entries, Period $period) => $entries->whereBetween($entries->qualifyColumn('date'), [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ]))
            ->when($filters['state'] ?? null, fn (Builder $entries, string $state) => $entries->where($entries->qualifyColumn('state'), $state));
    }

    /**
     * Limit the query to the Work Entries of the given Employees, on any of
     * their Assignments.
     *
     * @param  Builder<WorkEntry>  $query
     * @param  Builder<Employee>  $employees
     */
    #[QueryScope]
    protected function ofEmployees(Builder $query, Builder $employees): void
    {
        $query->whereIn('assignment_id', Assignment::query()
            ->select('id')
            ->whereIn('employee_id', $employees->select('employees.id')));
    }

    /**
     * The minutes as hours, such as 7.5.
     *
     * @return Attribute<float|int, never>
     */
    protected function hours(): Attribute
    {
        return Attribute::get(fn (): float|int => $this->minutes / 60);
    }
}
