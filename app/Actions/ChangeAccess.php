<?php

namespace App\Actions;

use App\Enums\AccessChange;
use App\Models\AccessLogEntry;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to Roles and who holds them. Each change is written to the
 * Access Log, with who made it, in the same transaction as the change.
 */
class ChangeAccess
{
    /**
     * Create a Role with the given Permissions, each mapped to its Scope or
     * to null for company-wide Permissions.
     *
     * @param  array<string, string|null>  $grants
     */
    public function createRole(Employee $actor, string $name, array $grants): Role
    {
        return DB::transaction(function () use ($actor, $name, $grants): Role {
            $role = Role::query()->create(['name' => $name]);
            $this->grant($role, $grants);

            $this->log($actor, AccessChange::RoleCreated, $role, after: $this->snapshot($role));

            return $role;
        });
    }

    /**
     * Rename the Role and replace its Permissions and Scopes.
     *
     * @param  array<string, string|null>  $grants
     */
    public function editRole(Employee $actor, Role $role, string $name, array $grants): void
    {
        DB::transaction(function () use ($actor, $role, $name, $grants): void {
            $someoneCouldManageRoles = $this->someoneCanManageRoles();
            $before = $this->snapshot($role);

            $role->update(['name' => $name]);
            $role->permissions()->delete();
            $this->grant($role, $grants);

            $this->ensureSomeoneStillCanManageRoles($someoneCouldManageRoles);
            $this->log($actor, AccessChange::RoleEdited, $role, before: $before, after: $this->snapshot($role));
        });
    }

    /**
     * Delete the Role, taking it from everyone who holds it.
     */
    public function deleteRole(Employee $actor, Role $role): void
    {
        DB::transaction(function () use ($actor, $role): void {
            $someoneCouldManageRoles = $this->someoneCanManageRoles();
            $before = [...$this->snapshot($role), 'holders' => $role->employees()->orderBy('employees.id')->pluck('employees.id')->all()];

            $role->delete();

            $this->ensureSomeoneStillCanManageRoles($someoneCouldManageRoles);
            $this->log($actor, AccessChange::RoleDeleted, $role, before: $before);
        });
    }

    /**
     * Give the Role to an Employee.
     */
    public function giveRole(Employee $actor, Role $role, Employee $employee): void
    {
        DB::transaction(function () use ($actor, $role, $employee): void {
            $role->employees()->attach($employee);

            $this->log($actor, AccessChange::RoleGiven, $role, $employee);
        });
    }

    /**
     * Take the Role away from an Employee who holds it.
     */
    public function takeRole(Employee $actor, Role $role, Employee $employee): void
    {
        DB::transaction(function () use ($actor, $role, $employee): void {
            $someoneCouldManageRoles = $this->someoneCanManageRoles();
            $role->employees()->detach($employee);

            $this->ensureSomeoneStillCanManageRoles($someoneCouldManageRoles);
            $this->log($actor, AccessChange::RoleTaken, $role, $employee);
        });
    }

    /**
     * Refuse the change, undoing it, when it leaves nobody who can manage
     * Roles from now on. A change that doesn't make things worse is let
     * through, so an unrelated edit is never blocked.
     *
     * @throws ValidationException
     */
    private function ensureSomeoneStillCanManageRoles(bool $someoneCouldBefore): void
    {
        if ($someoneCouldBefore && ! $this->someoneCanManageRoles()) {
            throw ValidationException::withMessages([
                'role' => __('Nobody else could manage Roles after this change, so it was refused. Give manage roles to another current Employee first.'),
            ]);
        }
    }

    /**
     * Whether any Employee can manage Roles from now on.
     */
    private function someoneCanManageRoles(): bool
    {
        return Employee::query()->lastingRoleManagers()->exists();
    }

    /**
     * Give the Role exactly these Permissions and Scopes.
     *
     * @param  array<string, string|null>  $grants
     */
    private function grant(Role $role, array $grants): void
    {
        foreach ($grants as $permission => $scope) {
            $role->permissions()->create(['permission' => $permission, 'scope' => $scope]);
        }
    }

    /**
     * The Role's name and Permissions as the Access Log keeps them.
     *
     * @return array{name: string, permissions: array<string, string|null>}
     */
    private function snapshot(Role $role): array
    {
        return ['name' => $role->name, 'permissions' => $role->load('permissions')->grants()];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function log(Employee $actor, AccessChange $change, Role $role, ?Employee $employee = null, ?array $before = null, ?array $after = null): void
    {
        AccessLogEntry::query()->create([
            'change' => $change,
            'actor_id' => $actor->id,
            'role_id' => $role->exists ? $role->id : null,
            'role_name' => $role->name,
            'employee_id' => $employee?->id,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
