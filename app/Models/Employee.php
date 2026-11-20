<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Concerns\HasAccess;
use App\Notifications\Invitation;
use App\Support\PaidDaysLeft;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $department_id
 * @property Carbon $start_date
 * @property Carbon|null $leaving_date
 * @property int|null $manager_id
 * @property int $paid_days
 * @property int|null $effective_manager_id Only on Employees queried with withEffectiveManager()
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'department_id', 'start_date', 'leaving_date', 'manager_id'])] // @pest-mutate-ignore (class attributes never show as covered)
#[Hidden(['password', 'remember_token'])] // @pest-mutate-ignore (class attributes never show as covered)
#[UseEloquentBuilder(EmployeeBuilder::class)] // @pest-mutate-ignore (class attributes never show as covered)
class Employee extends Authenticatable implements MustVerifyEmail
{
    use HasAccess;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, Notifiable;

    /**
     * A new Employee starts with no Paid Days, as the database does.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'paid_days' => 0,
    ];

    /** @var array<int, int>|null */
    private ?array $reportIds = null;

    /** @var EloquentCollection<int, Employee>|null */
    private ?EloquentCollection $managed = null;

    /**
     * Kept until forgetAccess(), as the sidebar and the page both ask.
     *
     * @var array{workEntries: int, timeOff: int, travelRequests: int, travelExpenses: int}|null
     */
    private ?array $waiting = null;

    /**
     * Stored in lowercase, so capitals never make two addresses different.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $email): string => Str::lower($email));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', // @pest-mutate-ignore (only read through hasVerifiedEmail, which works uncast)
            'password' => 'hashed',
            'start_date' => 'date',
            'leaving_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasManyThrough<WorkEntry, Assignment, $this> */
    public function workEntries(): HasManyThrough
    {
        return $this->hasManyThrough(WorkEntry::class, Assignment::class);
    }

    /** @return HasMany<TimeOff, $this> */
    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class);
    }

    /** @return HasMany<TravelRequest, $this> */
    public function travelRequests(): HasMany
    {
        return $this->hasMany(TravelRequest::class);
    }

    /** @return HasMany<PerformanceEvaluation, $this> */
    public function performanceEvaluations(): HasMany
    {
        return $this->hasMany(PerformanceEvaluation::class);
    }

    /** @return HasManyThrough<TravelExpense, TravelRequest, $this> */
    public function travelExpenses(): HasManyThrough
    {
        return $this->hasManyThrough(TravelExpense::class, TravelRequest::class);
    }

    /** Negative when their Paid Days were lowered below what is already taken. */
    public function paidDaysLeftIn(int $year): int
    {
        return PaidDaysLeft::in($this, [$year])[0]['days'];
    }

    /** @return list<array{year: int, uses: int, left: int}> */
    public function paidDaysUsedBy(TimeOff $timeOff): array
    {
        return array_map(fn (int $year): array => [
            'year' => $year,
            'uses' => $timeOff->weekdaysIn($year),
            'left' => $this->paidDaysLeftIn($year),
        ], range($timeOff->first_date->year, $timeOff->last_date->year));
    }

    /** @return HasOne<Assignment, $this> */
    public function internalAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->internal();
    }

    /**
     * Shaped as an Assignment's dates, which the Internal Assignment always has.
     *
     * @return array{start_date: Carbon, end_date: Carbon|null}
     */
    public function workingPeriod(): array
    {
        return ['start_date' => $this->start_date, 'end_date' => $this->leaving_date];
    }

    /** No Job Title for the installer, who isn't asked for one. */
    public function createInternalAssignment(?string $jobTitle = null): void
    {
        $this->assignments()->create([
            'project_id' => Project::internal()->id,
            'job_title' => $jobTitle,
            ...$this->workingPeriod(),
            'capacity' => 0,
        ]);
    }

    /** @return array<'start_date'|'end_date', string> */
    public function assignmentRefusals(CarbonInterface $start, ?CarbonInterface $end): array
    {
        $refusals = [];

        if ($start->isBefore($this->start_date)) {
            $refusals['start_date'] = __(':name\'s Start Date is :date, so the Assignment can\'t start before it.', ['name' => $this->name, 'date' => $this->start_date->format('j M Y')]);
        }

        if ($this->leaving_date !== null && ($end === null || $end->isAfter($this->leaving_date))) {
            $refusals['end_date'] = __(':name\'s Leaving Date is :date, so the Assignment must end by then.', ['name' => $this->name, 'date' => $this->leaving_date->format('j M Y')]);
        }

        return $refusals;
    }

    /** @return BelongsTo<Employee, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Only loads on Employees queried with withEffectiveManager().
     *
     * @return BelongsTo<Employee, $this>
     */
    public function effectiveManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'effective_manager_id');
    }

    /**
     * Managers who have left are skipped, as finding the effective Manager skips them.
     *
     * @return Collection<int, Employee>
     */
    public function reportingChain(): Collection
    {
        $above = self::query()->aboveInReportingLine($this)->get()->keyBy('id');
        $managerOf = fn (Employee $employee): ?Employee => $employee->manager_id === null ? null : $above->pull($employee->manager_id);
        $chain = new EloquentCollection;

        for ($manager = $managerOf($this); $manager !== null; $manager = $managerOf($manager)) {
            if ($manager->employmentStatus() !== EmploymentStatus::Former) {
                $chain->push($manager);
            }
        }

        return $chain->load(['department', 'internalAssignment']);
    }

    /** @return Collection<int, Employee> */
    public function managedEmployees(): Collection
    {
        return $this->managedEmployeesQuery()
            ->with(['department', 'internalAssignment'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function hasDirectReports(): bool
    {
        return self::anyStaying($this->effectivelyManaged());
    }

    public function hasNoManager(): bool
    {
        return $this->manager_id === null
            || self::query()->withEffectiveManager()->whereKey($this->id)->value('effective_manager_id') === null;
    }

    /** Former Employees count, as their Work Entries still need approving. */
    public function managesAnyone(): bool
    {
        return $this->effectivelyManaged()->isNotEmpty();
    }

    /**
     * The reporting line is walked once, not once per count.
     *
     * @return array{workEntries: int, timeOff: int, travelRequests: int, travelExpenses: int}
     */
    public function waitingForDecision(): array
    {
        return $this->waiting ??= $this->effectivelyManaged()->isEmpty()
            ? ['workEntries' => 0, 'timeOff' => 0, 'travelRequests' => 0, 'travelExpenses' => 0]
            : $this->waitingFor($this->effectivelyManaged()->modelKeys());
    }

    /**
     * Shares waitingForDecision()'s single walk down the reporting line.
     *
     * @return array{managesAnyone: bool, hasDirectReports: bool, waiting: int}
     */
    public function asManager(): array
    {
        return [
            'managesAnyone' => $this->managesAnyone(),
            'hasDirectReports' => $this->hasDirectReports(),
            'waiting' => array_sum($this->waitingForDecision()),
        ];
    }

    /**
     * One level below a Manager is few Employees, so they are read whole, once.
     *
     * @return EloquentCollection<int, Employee>
     */
    private function effectivelyManaged(): EloquentCollection
    {
        return $this->managed ??= self::query()->effectivelyManagedBy($this)->get(['id', 'start_date', 'leaving_date']);
    }

    /** @param  EloquentCollection<int, Employee>  $managed */
    private static function anyStaying(EloquentCollection $managed): bool
    {
        return $managed->contains(fn (Employee $employee): bool => $employee->employmentStatus() !== EmploymentStatus::Former);
    }

    /**
     * @param  array<int, int|string>  $decidedFor
     * @return array{workEntries: int, timeOff: int, travelRequests: int, travelExpenses: int}
     */
    private function waitingFor(array $decidedFor): array
    {
        return [
            'workEntries' => WorkEntry::query()
                ->pending()
                ->ofEmployees(self::query()->whereKey($decidedFor))
                ->count(),
            'timeOff' => TimeOff::query()
                ->pending()
                ->ofEmployees(self::query()->whereKey($decidedFor))
                ->count(),
            'travelRequests' => TravelRequest::query()
                ->pending()
                ->ofEmployees(self::query()->whereKey($decidedFor))
                ->count(),
            'travelExpenses' => TravelExpense::query()
                ->pending()
                ->ofEmployees(self::query()->whereKey($decidedFor))
                ->count(),
        ];
    }

    private function managedEmployeesQuery(): EmployeeBuilder
    {
        return self::query()
            ->effectivelyManagedBy($this)
            ->notFormer();
    }

    public function employmentStatus(): EmploymentStatus
    {
        return EmploymentStatus::on(today(), $this->start_date, $this->leaving_date);
    }

    public function signInRefusal(): ?string
    {
        return match ($this->employmentStatus()) {
            EmploymentStatus::Future => 'You can sign in from your Start Date, '.$this->start_date->format('j F Y').'.',
            EmploymentStatus::Former => 'You can no longer sign in because you have left the company.',
            EmploymentStatus::Current => null,
        };
    }

    /** A Manager who has left still links the chain, so their Reports stay below the next Manager up. */
    public function hasInReports(Employee $other): bool
    {
        $this->reportIds ??= array_flip(self::query()->reportsOf($this)->pluck('employees.id')->all());

        return isset($this->reportIds[$other->id]);
    }

    protected function forgetReportingLine(): void
    {
        $this->reportIds = null;
        $this->managed = null;
        $this->waiting = null;
    }

    /** Setting the password from the invitation is what verifies the email. */
    public function hasPendingInvitation(): bool
    {
        return ! $this->hasVerifiedEmail();
    }

    public function sendInvitation(): void
    {
        $this->notify(new Invitation(Password::broker('invites')->createToken($this)));
    }

    public function hasValidInvitation(string $token): bool
    {
        /** @var PasswordBroker $invites */
        $invites = Password::broker('invites');

        return $invites->tokenExists($this, $token);
    }
}
