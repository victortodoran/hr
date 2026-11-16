<?php

namespace App\Actions;

use App\Enums\AccessChange;
use App\Models\AccessLogEntry;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to Roles and who holds them. Each change is written to the
 * Access Log, with who made it, in the same transaction as the change. At
 * most one Role is given to every new Employee: giving a Role to them takes
 * that from any other.
 */
class ChangeAccess
{
    /**
     * Create a Role with the given Permissions, each mapped to its Scope or
     * to null for company-wide Permissions, and say whether every new
     * Employee gets it, and, when given, who holds it.
     *
     * @param  array<string, string|null>  $grants
     * @param  array<int, int>|null  $holders
     */
    public function createRole(Employee $actor, string $name, array $grants, bool $givenToNewEmployees, ?array $holders = null): Role
    {
        return DB::transaction(function () use ($actor, $name, $grants, $givenToNewEmployees, $holders): Role {
            if ($givenToNewEmployees) {
                $this->takeDefaultFromOthers($actor, null);
            }

            $role = Role::query()->create(['name' => $name, 'is_given_to_new_employees' => $givenToNewEmployees]);
            $this->grant($role, $grants);

            $this->log($actor, AccessChange::RoleCreated, $role, after: $this->snapshot($role));
            $this->holdBy($actor, $role, $holders);

            return $role;
        });
    }

    /**
     * Create the one Admin Role, which always holds every Permission, so it
     * stores no grants. New Employees don't get it.
     */
    public function createAdminRole(Employee $actor, string $name): Role
    {
        return DB::transaction(function () use ($actor, $name): Role {
            $role = new Role(['name' => $name, 'is_given_to_new_employees' => false]);
            $role->is_admin = true;
            $role->save();

            $this->log($actor, AccessChange::RoleCreated, $role, after: $this->snapshot($role));

            return $role;
        });
    }

    /**
     * Rename the Role, replace its Permissions and Scopes, and say whether
     * every new Employee gets it. Admin always holds every Permission, so
     * the grants sent for it are ignored, and it is never given to every
     * new Employee. When holders are given, the Role is given to and taken
     * from Employees so exactly they hold it. Everything is refused together
     * when it leaves nobody able to manage Roles.
     *
     * @param  array<string, string|null>  $grants
     * @param  array<int, int>|null  $holders
     */
    public function editRole(Employee $actor, Role $role, string $name, array $grants, bool $givenToNewEmployees, ?array $holders = null): void
    {
        DB::transaction(function () use ($actor, $role, $name, $grants, $givenToNewEmployees, $holders): void {
            $someoneCouldManageRoles = $this->someoneCanManageRoles();
            $before = $this->snapshot($role);

            if ($givenToNewEmployees && ! $role->is_admin) {
                $this->takeDefaultFromOthers($actor, $role);
            }

            $role->update(['name' => $name, 'is_given_to_new_employees' => $givenToNewEmployees && ! $role->is_admin]);

            if (! $role->is_admin) {
                $role->permissions()->delete();
                $this->grant($role, $grants);
            }

            $this->log($actor, AccessChange::RoleEdited, $role, before: $before, after: $this->snapshot($role));
            $this->holdBy($actor, $role, $holders);
            $this->ensureSomeoneStillCanManageRoles($someoneCouldManageRoles);
        });
    }

    /**
     * Delete the Role, taking it from everyone who holds it. Admin can't be
     * deleted.
     *
     * @throws ValidationException
     */
    public function deleteRole(Employee $actor, Role $role): void
    {
        if ($role->is_admin) {
            throw ValidationException::withMessages([
                'role' => __('Admin always holds every Permission, so it can\'t be deleted.'),
            ]);
        }

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
     * Give the Role to each of the Employees, all or none.
     *
     * @param  iterable<int, Employee>  $employees
     */
    public function giveRoleToEach(Employee $actor, Role $role, iterable $employees): void
    {
        DB::transaction(function () use ($actor, $role, $employees): void {
            foreach ($employees as $employee) {
                $this->giveRole($actor, $role, $employee);
            }
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
     * Give the Role to the listed Employees who don't hold it and take it
     * from those who hold it but aren't listed, logging each; nothing when
     * no list is given.
     *
     * @param  array<int, int>|null  $holders
     */
    private function holdBy(Employee $actor, Role $role, ?array $holders): void
    {
        if ($holders === null) {
            return;
        }

        $current = $role->employees()->pluck('employees.id')->all();

        Employee::query()->whereKey(array_diff($holders, $current))->orderBy('id')->get()
            ->each(fn (Employee $employee) => $this->giveRole($actor, $role, $employee));
        Employee::query()->whereKey(array_diff($current, $holders))->orderBy('id')->get()
            ->each(fn (Employee $employee) => $this->takeRole($actor, $role, $employee));
    }

    /**
     * Stop giving every other Role to new Employees, logging each, so at
     * most one Role is given to them.
     */
    private function takeDefaultFromOthers(Employee $actor, ?Role $keep): void
    {
        Role::query()
            ->where('is_given_to_new_employees', true)
            ->when($keep, fn (Builder $roles, Role $role) => $roles->whereKeyNot($role->id))
            ->get()
            ->each(function (Role $previous) use ($actor): void {
                $before = $this->snapshot($previous);
                $previous->update(['is_given_to_new_employees' => false]);
                $this->log($actor, AccessChange::RoleEdited, $previous, before: $before, after: $this->snapshot($previous));
            });
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
     * The Role's name, Permissions and whether new Employees get it, as the
     * Access Log keeps them. Admin's Permissions are not stored, so it is
     * marked as Admin, meaning every Permission, instead.
     *
     * @return array{name: string, permissions?: array<string, string|null>, admin?: true, given_to_new_employees: bool}
     */
    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            ...($role->is_admin ? ['admin' => true] : ['permissions' => $role->load('permissions')->grants()]),
            'given_to_new_employees' => $role->is_given_to_new_employees,
        ];
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
