<?php

namespace App\Actions\Access;

use App\Enums\AccessChange;
use App\Models\AccessLogEntry;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeAccess
{
    /**
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

    /** Admin always holds every Permission, so it stores no grants. */
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
     * Admin always holds every Permission, so the grants sent for it are
     * ignored.
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

    /** @throws ValidationException */
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

    public function giveRole(Employee $actor, Role $role, Employee $employee): void
    {
        DB::transaction(function () use ($actor, $role, $employee): void {
            $role->employees()->attach($employee);

            $this->log($actor, AccessChange::RoleGiven, $role, $employee);
        });
    }

    /** @param  iterable<int, Employee>  $employees */
    public function giveRoleToEach(Employee $actor, Role $role, iterable $employees): void
    {
        DB::transaction(function () use ($actor, $role, $employees): void {
            foreach ($employees as $employee) {
                $this->giveRole($actor, $role, $employee);
            }
        });
    }

    /** @throws ModelNotFoundException<Employee> */
    public function takeRole(Employee $actor, Role $role, Employee $employee): void
    {
        $role->employees()->findOrFail($employee->id);

        $this->take($actor, $role, $employee);
    }

    private function take(Employee $actor, Role $role, Employee $employee): void
    {
        DB::transaction(function () use ($actor, $role, $employee): void {
            $someoneCouldManageRoles = $this->someoneCanManageRoles();
            $role->employees()->detach($employee);

            $this->ensureSomeoneStillCanManageRoles($someoneCouldManageRoles);
            $this->log($actor, AccessChange::RoleTaken, $role, $employee);
        });
    }

    /**
     * A change that doesn't make things worse is let through, so an unrelated
     * edit is never blocked.
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

    private function someoneCanManageRoles(): bool
    {
        return Employee::query()->lastingRoleManagers()->exists();
    }

    /** @param  array<int, int>|null  $holders */
    private function holdBy(Employee $actor, Role $role, ?array $holders): void
    {
        if ($holders === null) {
            return;
        }

        $current = $role->employees()->pluck('employees.id')->all();

        Employee::query()->whereKey(array_diff($holders, $current))->orderBy('id')->get()
            ->each(fn (Employee $employee) => $this->giveRole($actor, $role, $employee));
        Employee::query()->whereKey(array_diff($current, $holders))->orderBy('id')->get()
            ->each(fn (Employee $employee) => $this->take($actor, $role, $employee));
    }

    /** At most one Role is given to new Employees. */
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

    /** @param  array<string, string|null>  $grants */
    private function grant(Role $role, array $grants): void
    {
        foreach ($grants as $permission => $scope) {
            $role->permissions()->create(['permission' => $permission, 'scope' => $scope]);
        }
    }

    /**
     * Admin's Permissions are not stored, so it is marked as Admin, meaning
     * every Permission, instead.
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
     * Every change passes through here, so it also makes the actor, who may have
     * changed their own Roles, and the Employee given or losing a Role read
     * their access afresh.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function log(Employee $actor, AccessChange $change, Role $role, ?Employee $employee = null, ?array $before = null, ?array $after = null): void
    {
        $actor->forgetAccess();
        $employee?->forgetAccess();

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
