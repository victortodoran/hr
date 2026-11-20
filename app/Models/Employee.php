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
 * @property int $paid_days How many days of Paid Time Off they get each calendar year, the same number for every year
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

    /**
     * The ids of the Employee's Reports, as keys for a quick lookup.
     *
     * @var array<int, int>|null
     */
    private ?array $reportIds = null;

    /**
     * The Employees whose effective Manager this Employee is, read on the
     * first check and kept until forgetAccess().
     *
     * @var EloquentCollection<int, Employee>|null
     */
    private ?EloquentCollection $managed = null;

    /**
     * What waits for this Employee to decide, counted on the first ask and
     * kept until forgetAccess(), as the sidebar and the page both ask.
     *
     * @var array{workEntries: int, timeOff: int, travelRequests: int, travelExpenses: int}|null
     */
    private ?array $waiting = null;

    /**
     * Emails are stored in lowercase, so capitals never make two addresses
     * different.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $email): string => Str::lower($email));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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

    /**
     * The Projects the Employee works on, and when.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * The hours worked on the Employee's Assignments.
     *
     * @return HasManyThrough<WorkEntry, Assignment, $this>
     */
    public function workEntries(): HasManyThrough
    {
        return $this->hasManyThrough(WorkEntry::class, Assignment::class);
    }

    /**
     * The Time Off the Employee has requested, in every state.
     *
     * @return HasMany<TimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class);
    }

    /**
     * The Travel Requests the Employee has made, in every state.
     *
     * @return HasMany<TravelRequest, $this>
     */
    public function travelRequests(): HasMany
    {
        return $this->hasMany(TravelRequest::class);
    }

    /**
     * The Employee's Performance Evaluations, at most one per year.
     *
     * @return HasMany<PerformanceEvaluation, $this>
     */
    public function performanceEvaluations(): HasMany
    {
        return $this->hasMany(PerformanceEvaluation::class);
    }

    /**
     * The Travel Expenses the Employee recorded on their Travel Requests,
     * in every state.
     *
     * @return HasManyThrough<TravelExpense, TravelRequest, $this>
     */
    public function travelExpenses(): HasManyThrough
    {
        return $this->hasManyThrough(TravelExpense::class, TravelRequest::class);
    }

    /**
     * How many Paid Days the Employee has left in a year: their Paid Days,
     * which every year gets in full, minus the weekdays that year of their
     * Pending and Approved Paid Time Off. It is negative when their Paid Days
     * were lowered below what is already taken.
     */
    public function paidDaysLeftIn(int $year): int
    {
        return PaidDaysLeft::in($this, [$year])[0]['days'];
    }

    /**
     * What Paid Time Off not yet requested would take from each year it
     * touches: the weekdays falling in that year, and the Paid Days left in
     * it before them.
     *
     * @return list<array{year: int, uses: int, left: int}>
     */
    public function paidDaysUsedBy(TimeOff $timeOff): array
    {
        return array_map(fn (int $year): array => [
            'year' => $year,
            'uses' => $timeOff->weekdaysIn($year),
            'left' => $this->paidDaysLeftIn($year),
        ], range($timeOff->first_date->year, $timeOff->last_date->year));
    }

    /**
     * Their Assignment to the Internal Project, for their whole period of
     * work.
     *
     * @return HasOne<Assignment, $this>
     */
    public function internalAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->internal();
    }

    /**
     * The Employee's period of work as an Assignment's dates, which is what
     * their Internal Assignment always has. It is open-ended until a Leaving
     * Date is set.
     *
     * @return array{start_date: Carbon, end_date: Carbon|null}
     */
    public function workingPeriod(): array
    {
        return ['start_date' => $this->start_date, 'end_date' => $this->leaving_date];
    }

    /**
     * Put the Employee on the Internal Project for their whole period of
     * work, with the internal Job Title given (none for the installer, who
     * isn't asked for one) and 0% capacity.
     */
    public function createInternalAssignment(?string $jobTitle = null): void
    {
        $this->assignments()->create([
            'project_id' => Project::internal()->id,
            'job_title' => $jobTitle,
            ...$this->workingPeriod(),
            'capacity' => 0,
        ]);
    }

    /**
     * Why an Assignment over the period wouldn't fit the Employee's working
     * period, by the field to blame; empty when it fits. A null end means
     * the Assignment is open-ended.
     *
     * @return array<'start_date'|'end_date', string>
     */
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

    /**
     * The Employee they report to, if any; any number of Employees have none.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * The Employee they effectively report to: their Manager, or while that
     * Manager has left, the first Employee up the chain who hasn't. Only
     * loads on Employees queried with withEffectiveManager().
     *
     * @return BelongsTo<Employee, $this>
     */
    public function effectiveManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'effective_manager_id');
    }

    /**
     * Everyone above the Employee in their Reporting Chain: their effective
     * Manager, that Manager's effective Manager, and so on up to an Employee
     * with no Manager, nearest first. Everyone above is read at once, and
     * those who have left are skipped, as finding the effective Manager
     * skips them.
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

    /**
     * The Employees one level below in the Employee's Reporting Chain: those
     * whose effective Manager they are, so the Employees of a Manager who
     * has left count under the next Manager up. Future Employees are
     * included and Former ones left out. In name order.
     *
     * @return Collection<int, Employee>
     */
    public function managedEmployees(): Collection
    {
        return $this->managedEmployeesQuery()
            ->with(['department', 'internalAssignment'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether the Employee has any Direct Reports: Employees, Future ones
     * included, whose effective Manager they are.
     */
    public function hasDirectReports(): bool
    {
        return self::anyStaying($this->effectivelyManaged());
    }

    /**
     * Whether the Employee has no Manager: none is recorded, or every Manager
     * up the chain has left. Nobody could decide for them, so they record
     * no Work Entries and their Time Off is Approved as soon as requested.
     */
    public function hasNoManager(): bool
    {
        return $this->manager_id === null
            || self::query()->withEffectiveManager()->whereKey($this->id)->value('effective_manager_id') === null;
    }

    /**
     * Whether the Employee is the effective Manager of anyone, Former
     * Employees included, and so approves someone's Work Entries.
     */
    public function managesAnyone(): bool
    {
        return $this->effectivelyManaged()->isNotEmpty();
    }

    /**
     * What is waiting for this Employee to decide: the Pending Work Entries,
     * Time Off, Travel Requests and Travel Expenses of the Employees whose
     * effective Manager they are, the same Employees they approve for. The reporting line is
     * walked once, not once per count.
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
     * What the sidebar shows of the Employee as a Manager, from the same
     * walk down the reporting line as waitingForDecision(): whether they
     * manage anyone (as managesAnyone()), whether they have Direct Reports
     * (as hasDirectReports()), and how much is waiting for them to decide.
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
     * The Employees whose effective Manager this Employee is, Former ones
     * included, with just enough to tell whether they have left. One level
     * below a Manager is few Employees, so they are read whole, once.
     *
     * @return EloquentCollection<int, Employee>
     */
    private function effectivelyManaged(): EloquentCollection
    {
        return $this->managed ??= self::query()->effectivelyManagedBy($this)->get(['id', 'start_date', 'leaving_date']);
    }

    /**
     * Whether any of the given Employees hasn't left: Direct Reports count
     * Future and current Employees, not Former ones.
     *
     * @param  EloquentCollection<int, Employee>  $managed
     */
    private static function anyStaying(EloquentCollection $managed): bool
    {
        return $managed->contains(fn (Employee $employee): bool => $employee->employmentStatus() !== EmploymentStatus::Former);
    }

    /**
     * The Pending Work Entries, Time Off, Travel Requests and Travel
     * Expenses of the given Employees.
     *
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

    /**
     * The Employees whose effective Manager this Employee is, Former ones
     * left out.
     */
    private function managedEmployeesQuery(): EmployeeBuilder
    {
        return self::query()
            ->effectivelyManagedBy($this)
            ->notFormer();
    }

    /**
     * Whether the Employee is future, current or former today.
     */
    public function employmentStatus(): EmploymentStatus
    {
        return EmploymentStatus::on(today(), $this->start_date, $this->leaving_date);
    }

    /**
     * Why the Employee may not sign in today, or null when they may.
     */
    public function signInRefusal(): ?string
    {
        return match ($this->employmentStatus()) {
            EmploymentStatus::Future => 'You can sign in from your Start Date, '.$this->start_date->format('j F Y').'.',
            EmploymentStatus::Former => 'You can no longer sign in because you have left the company.',
            EmploymentStatus::Current => null,
        };
    }

    /**
     * Whether the other Employee is one of this Employee's Reports: anywhere
     * below them in the reporting line. A Manager who has left still links the chain, so their Reports
     * stay below the next Manager up.
     */
    public function hasInReports(Employee $other): bool
    {
        $this->reportIds ??= array_flip(self::query()->reportsOf($this)->pluck('employees.id')->all());

        return isset($this->reportIds[$other->id]);
    }

    /**
     * Forget the Reports, the Employees managed and what waits for a
     * decision, for forgetAccess().
     */
    protected function forgetReportingLine(): void
    {
        $this->reportIds = null;
        $this->managed = null;
        $this->waiting = null;
    }

    /**
     * Whether the Employee has yet to set their password from the invitation.
     */
    public function hasPendingInvitation(): bool
    {
        return ! $this->hasVerifiedEmail();
    }

    /**
     * Email the Employee a link to set their password. Using it also
     * verifies their email.
     */
    public function sendInvitation(): void
    {
        $this->notify(new Invitation(Password::broker('invites')->createToken($this)));
    }

    /**
     * Whether the token is from the Employee's invitation and has not expired.
     */
    public function hasValidInvitation(string $token): bool
    {
        /** @var PasswordBroker $invites */
        $invites = Password::broker('invites');

        return $invites->tokenExists($this, $token);
    }
}
