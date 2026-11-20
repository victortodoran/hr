<?php

namespace App\Models\Concerns;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * See ADR 0004 for Roles, Permissions and Scopes.
 *
 * @mixin Employee
 *
 * @phpstan-require-extends Employee
 */
trait HasAccess
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function holds(Permission $permission): bool
    {
        return $this->grantsOf($permission)->isNotEmpty();
    }

    /** The Work page opens on their own Work Entries, so reaching themselves is what counts. */
    public function canSeeWorkEntries(): bool
    {
        return $this->reachesThrough(Permission::seeingWorkEntries(), $this);
    }

    public function seesOthersWorkEntries(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingWorkEntries());
    }

    public function seesOthersTravel(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingTravel());
    }

    public function seesOthersTimeOff(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingTimeOff());
    }

    public function seesOthersPerformanceEvaluations(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingPerformanceEvaluations());
    }

    /** @return list<Scope> */
    public function reachOf(Permission $permission): array
    {
        if ($permission->isScoped()) {
            return array_values($this->grantsOf($permission)
                ->map(fn (RolePermission $grant): ?Scope => $grant->scope)
                ->filter()
                ->uniqueStrict()
                ->all());
        }

        return $this->holds($permission) ? [Scope::Everyone] : [];
    }

    /** @param  list<Permission>  $permissions */
    public function reachesThrough(array $permissions, Employee $target): bool
    {
        return $this->scopesGivenFor($permissions)->contains(fn (Scope $scope): bool => $scope->covers($this, $target));
    }

    /** @param  list<Permission>  $permissions */
    public function reachesOthersThrough(array $permissions): bool
    {
        return $this->othersReachThrough($permissions) !== null;
    }

    /** @param  list<Permission>  $permissions */
    public function othersReachThrough(array $permissions): ?Scope
    {
        $scopes = $this->scopesGivenFor($permissions);

        if ($scopes->contains(Scope::Everyone)) {
            return Scope::Everyone;
        }

        return $scopes->contains(fn (Scope $scope): bool => $scope->coversOthers()) ? Scope::Reports : null;
    }

    /**
     * The query counterpart of reachesThrough(), so the two must agree.
     *
     * @param  list<Permission>  $permissions
     */
    public function limitToReachOf(EmployeeBuilder $employees, array $permissions): void
    {
        $scopes = $this->scopesGivenFor($permissions);

        $employees->where(function (EmployeeBuilder $covered) use ($scopes): void {
            $covered->whereRaw('false');
            $scopes->each(fn (Scope $scope) => $covered->orWhere(fn (EmployeeBuilder $employee) => $scope->limit($employee, $this)));
        });
    }

    /**
     * Lets pages offer only what the Employee may use.
     *
     * @return array<int, string>
     */
    public function companyWidePermissions(): array
    {
        if ($this->holdsAdmin()) {
            return collect(Permission::cases())
                ->reject(fn (Permission $permission): bool => $permission->isScoped())
                ->map(fn (Permission $permission): string => $permission->value)
                ->sort()
                ->values()
                ->all();
        }

        return $this->grantsHeld()
            ->pluck('permission')
            ->reject(fn (Permission $permission): bool => $permission->isScoped())
            ->map(fn (Permission $permission): string => $permission->value)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function holdsAdmin(): bool
    {
        return $this->rolesWithGrants()->contains(fn (Role $role): bool => $role->is_admin);
    }

    /** Refreshing the Employee reloads the Roles but not the Reports, so a change to either while the Employee is in hand needs this. */
    public function forgetAccess(): void
    {
        $this->unsetRelation('roles');
        $this->forgetReportingLine();
    }

    /**
     * @param  list<Permission>  $permissions
     * @return Collection<int, Scope>
     */
    private function scopesGivenFor(array $permissions): Collection
    {
        return collect($permissions)
            ->flatMap(fn (Permission $permission): array => $this->reachOf($permission))
            ->values();
    }

    /**
     * Loaded once and kept, as every Permission check needs them.
     *
     * @return EloquentCollection<int, Role>
     */
    private function rolesWithGrants(): EloquentCollection
    {
        return $this->loadMissing('roles.permissions')->roles;
    }

    /** @return Collection<int, RolePermission> */
    private function grantsHeld(): Collection
    {
        return $this->rolesWithGrants()->flatMap(fn (Role $role) => $role->permissions);
    }

    /**
     * The Admin Role stores no grants, so its holders get the one grant it always has.
     *
     * @return Collection<int, RolePermission>
     */
    private function grantsOf(Permission $permission): Collection
    {
        if ($this->holdsAdmin()) {
            return collect([new RolePermission([
                'permission' => $permission, // @pest-mutate-ignore (callers only read the grant's Scope)
                'scope' => $permission->isScoped() ? Scope::Everyone : null,
            ])]);
        }

        return $this->grantsHeld()
            ->filter(fn (RolePermission $grant): bool => $grant->permission === $permission)
            ->values();
    }
}
