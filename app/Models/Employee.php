<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Notifications\Invitation;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope as QueryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $department_id
 * @property Carbon $start_date
 * @property Carbon|null $leaving_date
 * @property int|null $manager_id
 * @property bool $is_top
 * @property int|null $effective_manager_id Only on Employees queried with withEffectiveManager()
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'department_id', 'start_date', 'leaving_date', 'manager_id'])] // @pest-mutate-ignore (class attributes never show as covered)
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])] // @pest-mutate-ignore (class attributes never show as covered)
class Employee extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'is_top' => 'boolean', // @pest-mutate-ignore (PostgreSQL already returns a boolean)
            'start_date' => 'date',
            'leaving_date' => 'date',
            'two_factor_confirmed_at' => 'datetime', // @pest-mutate-ignore (starter-kit two-factor code)
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
     * work, with no internal Job Title yet and 0% capacity.
     */
    public function createInternalAssignment(): void
    {
        $this->assignments()->create([
            'project_id' => Project::internal()->id,
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
     * The Employee they report to; only the top Employee has none.
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
     * The Employee's Reporting Chain: their effective Manager, that
     * Manager's effective Manager, and so on up to the top Employee.
     *
     * @return Collection<int, Employee>
     */
    public function reportingChain(): Collection
    {
        $chain = collect();
        $managerId = self::query()->withEffectiveManager()->whereKey($this->id)->value('effective_manager_id');

        while ($managerId !== null) {
            $manager = self::query()->withEffectiveManager()->with(['department', 'internalAssignment'])->whereKey($managerId)->firstOrFail();
            $chain->push($manager);
            $managerId = $manager->effective_manager_id;
        }

        return $chain;
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
        return self::query()
            ->fromSub(self::query()->withEffectiveManager(), 'employees')
            ->where('effective_manager_id', $this->id)
            ->where(fn (Builder $staying) => $staying->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', today()))
            ->with(['department', 'internalAssignment'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
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
        return self::query()->reportsOf($this)->whereKey($other->id)->exists();
    }

    /**
     * Whether the Employee has yet to set their password from the invitation.
     */
    public function hasPendingInvitation(): bool
    {
        return ! $this->hasVerifiedEmail();
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
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

    /**
     * Whether any of the Employee's Roles grants the Permission.
     */
    public function holds(Permission $permission): bool
    {
        return $this->grantsOf($permission)->isNotEmpty();
    }

    /**
     * The widest Scope the Employee's Roles give them for a Permission about
     * Employees, or null when no Role grants it.
     */
    public function widestScopeFor(Permission $permission): ?Scope
    {
        return Scope::widest($this->grantsOf($permission)->pluck('scope')->filter());
    }

    /**
     * The company-wide Permissions (those without a Scope) the Employee's
     * Roles grant, so pages can offer only what the Employee may use.
     *
     * @return array<int, string>
     */
    public function companyWidePermissions(): array
    {
        return RolePermission::query()
            ->whereIn('role_id', $this->roles()->select('roles.id'))
            ->distinct()
            ->orderBy('permission')
            ->pluck('permission')
            ->reject(fn (Permission $permission): bool => $permission->isScoped())
            ->map(fn (Permission $permission): string => $permission->value)
            ->values()
            ->all();
    }

    /**
     * Limit the query to Employees who can manage Roles from now on: current
     * Employees with no Leaving Date whose Roles grant manage roles. Anyone
     * else can't sign in, or soon won't be able to.
     *
     * @param  Builder<Employee>  $query
     */
    #[QueryScope]
    protected function lastingRoleManagers(Builder $query): void
    {
        $query->whereDate('start_date', '<=', today())
            ->whereNull('leaving_date')
            ->whereHas('roles.permissions', fn (Builder $grants) => $grants->where('permission', Permission::ManageRoles));
    }

    /**
     * Limit the query to the Manager's Reports: everyone below them in the
     * reporting line, the same Employees hasInReports() accepts.
     *
     * @param  Builder<Employee>  $query
     */
    #[QueryScope]
    protected function reportsOf(Builder $query, Employee $manager): void
    {
        $query->whereRaw(<<<'SQL'
            employees.id IN (
                WITH RECURSIVE below AS (
                    SELECT id FROM employees WHERE manager_id = ?
                    UNION
                    SELECT employees.id FROM employees JOIN below ON employees.manager_id = below.id
                )
                SELECT id FROM below
            )
            SQL, [$manager->id]);
    }

    /**
     * Limit the query to Employees who are current today.
     *
     * @param  Builder<Employee>  $query
     */
    #[QueryScope]
    protected function current(Builder $query): void
    {
        $query->whereDate('start_date', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', today()));
    }

    /**
     * Limit the query to Employees who are future, current or former today.
     *
     * @param  Builder<Employee>  $query
     */
    #[QueryScope]
    protected function inEmploymentStatus(Builder $query, EmploymentStatus $status): void
    {
        match ($status) {
            EmploymentStatus::Future => $query->whereDate('start_date', '>', today()),
            EmploymentStatus::Current => $query->current(),
            EmploymentStatus::Former => $query->whereDate('leaving_date', '<', today()),
        };
    }

    /**
     * Add each Employee's effective_manager_id: walking up from their stored
     * Manager, the first Employee who hasn't left. It is worked out when read,
     * so nothing needs reassigning when a Manager leaves. The walk only climbs
     * past Managers who have left, so it ends at the one row that's picked.
     *
     * @param  Builder<Employee>  $query
     */
    #[QueryScope]
    protected function withEffectiveManager(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('employees.*');
        }

        $query->selectRaw(<<<'SQL'
            (
                WITH RECURSIVE up AS (
                    SELECT managers.id, managers.manager_id, managers.leaving_date
                    FROM employees AS managers WHERE managers.id = employees.manager_id
                    UNION ALL
                    SELECT managers.id, managers.manager_id, managers.leaving_date
                    FROM employees AS managers JOIN up ON managers.id = up.manager_id
                    WHERE up.leaving_date < ?
                )
                SELECT id FROM up WHERE up.leaving_date IS NULL OR up.leaving_date >= ? LIMIT 1
            ) AS effective_manager_id
            SQL, [today()->toDateString(), today()->toDateString()]);
    }

    /**
     * Every grant of the Permission across the Employee's Roles.
     *
     * @return Collection<int, RolePermission>
     */
    private function grantsOf(Permission $permission): Collection
    {
        return RolePermission::query()
            ->whereIn('role_id', $this->roles()->select('roles.id'))
            ->where('permission', $permission)
            ->get();
    }

    /**
     * Get the Employee's passkeys.
     *
     * laravel/passkeys stores the owner in a column named user_id, so the
     * foreign key is named explicitly rather than guessed from this model.
     *
     * @return HasMany<Passkey, Model>
     */
    public function passkeys(): HasMany
    {
        return self::passkeysOwnedBy($this);
    }

    /**
     * Build the passkeys relation for an owner typed as a plain Model, which
     * is the relation type the PasskeyUser contract declares.
     *
     * @return HasMany<Passkey, Model>
     */
    private static function passkeysOwnedBy(Model $owner): HasMany
    {
        return $owner->hasMany(Passkeys::passkeyModel(), 'user_id');
    }
}
