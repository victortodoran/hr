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
 * What an Employee may do: the Roles they hold and the Permissions those
 * grant, with the Scope each reaches (ADR 0004). Used by Employee only.
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

    /**
     * Whether any of the Employee's Roles grants the Permission.
     */
    public function holds(Permission $permission): bool
    {
        return $this->grantsOf($permission)->isNotEmpty();
    }

    /**
     * Whether the Employee may open the Work page: they may see their own,
     * as the policy decides.
     */
    public function canSeeWorkEntries(): bool
    {
        return $this->reachesThrough(Permission::seeingWorkEntries(), $this);
    }

    /**
     * Whether the Employee sees Work Entries of anyone besides themselves
     * across Employees: one of the Permissions for seeing them reaches
     * beyond themselves.
     */
    public function seesOthersWorkEntries(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingWorkEntries());
    }

    /**
     * Whether the Employee sees the Travel Requests of anyone besides
     * themselves across Employees: one of the Permissions for seeing travel
     * reaches beyond themselves.
     */
    public function seesOthersTravel(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingTravel());
    }

    /**
     * Whether the Employee sees the Time Off of anyone besides themselves
     * across Employees: one of the Permissions for seeing Time Off reaches
     * beyond themselves.
     */
    public function seesOthersTimeOff(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingTimeOff());
    }

    /**
     * Whether the Employee may read Performance Evaluations of anyone
     * besides themselves: one of the Permissions for reading them
     * (Permission::seeingPerformanceEvaluations()) reaches beyond
     * themselves.
     */
    public function seesOthersPerformanceEvaluations(): bool
    {
        return $this->reachesOthersThrough(Permission::seeingPerformanceEvaluations());
    }

    /**
     * How far a Permission reaches for the Employee: the widest Scope their
     * Roles give for one about Employees, Everyone for a company-wide one
     * they hold, or null when no Role grants it.
     */
    public function reachOf(Permission $permission): ?Scope
    {
        if ($permission->isScoped()) {
            return $this->widestScopeFor($permission);
        }

        return $this->holds($permission) ? Scope::Everyone : null;
    }

    /**
     * Whether any of the Permissions reaches the target Employee.
     *
     * @param  list<Permission>  $permissions
     */
    public function reachesThrough(array $permissions, Employee $target): bool
    {
        return collect($permissions)->contains(
            fn (Permission $permission): bool => $this->reachOf($permission)?->covers($this, $target) ?? false,
        );
    }

    /**
     * Whether any of the Permissions reaches anyone besides the Employee.
     *
     * @param  list<Permission>  $permissions
     */
    public function reachesOthersThrough(array $permissions): bool
    {
        return collect($permissions)->contains(
            fn (Permission $permission): bool => $this->reachOf($permission)?->coversOthers() ?? false,
        );
    }

    /**
     * Limit a query of Employees to those any of the Permissions reaches for
     * the Employee: the query counterpart of reachesThrough().
     *
     * @param  list<Permission>  $permissions
     */
    public function limitToReachOf(EmployeeBuilder $employees, array $permissions): void
    {
        $scopes = collect($permissions)
            ->map(fn (Permission $permission): ?Scope => $this->reachOf($permission))
            ->filter();

        $employees->where(function (EmployeeBuilder $covered) use ($scopes): void {
            $covered->whereRaw('false');
            $scopes->each(fn (Scope $scope) => $covered->orWhere(fn (EmployeeBuilder $employee) => $scope->limit($employee, $this)));
        });
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
     * Roles grant, all of them for an Admin holder, so pages can offer only
     * what the Employee may use.
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

    /**
     * Whether the Employee holds the Admin Role, which always holds every
     * Permission with the everyone Scope.
     */
    public function holdsAdmin(): bool
    {
        return $this->rolesWithGrants()->contains(fn (Role $role): bool => $role->is_admin);
    }

    /**
     * Forget the Roles and grants loaded so far, so the next check reads them
     * afresh: for when Roles or who holds them change while this Employee
     * is in hand. Refreshing the Employee does the same.
     */
    public function forgetAccess(): void
    {
        $this->unsetRelation('roles');
    }

    /**
     * The Employee's Roles with their grants, loaded once with the Employee
     * and kept, as every Permission check needs them.
     *
     * @return EloquentCollection<int, Role>
     */
    private function rolesWithGrants(): EloquentCollection
    {
        return $this->loadMissing('roles.permissions')->roles;
    }

    /**
     * Every grant across the Employee's Roles.
     *
     * @return Collection<int, RolePermission>
     */
    private function grantsHeld(): Collection
    {
        return $this->rolesWithGrants()->flatMap(fn (Role $role) => $role->permissions);
    }

    /**
     * Every grant of the Permission across the Employee's Roles. The Admin
     * Role stores no grants, so for its holders this is the one grant it
     * always has: everyone for a Permission about Employees.
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
