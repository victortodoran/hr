<?php

namespace App\Pages\Access;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\Role;

/**
 * The Roles page as the props it renders: every Role, by name, with its
 * Permissions and holders, the one selected, and whether it is viewed,
 * edited or a new one is being made; plus every Permission a Role can
 * contain, and every Employee who hasn't left, to give Roles to.
 */
final class RolesPage
{
    /**
     * The Roles page on the first Role by name.
     *
     * @return array<string, mixed>
     */
    public static function index(): array
    {
        return self::props('view', Role::query()->orderBy('name')->orderBy('id')->first());
    }

    /**
     * The Roles page on one Role.
     *
     * @return array<string, mixed>
     */
    public static function show(Role $role): array
    {
        return self::props('view', $role);
    }

    /**
     * The Roles page with a new Role being made.
     *
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        return self::props('create', null);
    }

    /**
     * The Roles page with one Role being edited.
     *
     * @return array<string, mixed>
     */
    public static function edit(Role $role): array
    {
        return self::props('edit', $role);
    }

    /**
     * @param  'view'|'edit'|'create'  $mode
     * @return array<string, mixed>
     */
    private static function props(string $mode, ?Role $selected): array
    {
        $roles = Role::query()
            ->with('permissions') // @pest-mutate-ignore (eager loading only saves queries)
            ->with(['employees' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Roles that share a name, which the unique index rules out)
            ->get();

        return [
            'mode' => $mode,
            'selected' => $selected?->id,
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_admin' => $role->is_admin,
                'permissions' => $role->grants(),
                'given_to_new_employees' => $role->is_given_to_new_employees,
                'holders' => $role->employees
                    ->map(fn (Employee $holder): array => [
                        'id' => $holder->id,
                        'name' => $holder->name,
                        'status' => $holder->employmentStatus()->value,
                    ])
                    ->all(),
            ])->all(),
            'permissions' => collect(Permission::cases())
                ->map(fn (Permission $permission): array => [
                    'value' => $permission->value,
                    'label' => $permission->label(),
                    'scopes' => $permission->scopes(),
                    'group' => $permission->group(),
                ])
                ->all(),
            'employees' => Employee::query()
                ->notFormer()
                ->orderBy('name')
                ->get(['id', 'name', 'start_date', 'leaving_date'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name, 'status' => $employee->employmentStatus()->value])
                ->values()
                ->all(),
        ];
    }
}
