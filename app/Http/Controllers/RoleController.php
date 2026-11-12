<?php

namespace App\Http\Controllers;

use App\Actions\ChangeAccess;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Http\Requests\RoleRequest;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets Employees who manage Roles shape them: their Permissions and Scopes,
 * whether new Employees get them, and who holds them.
 */
class RoleController extends Controller
{
    /**
     * Show every Role, by name, with its Permissions and holders, plus every
     * Permission a Role can contain and every Employee who hasn't left, to
     * give Roles to.
     */
    public function index(): Response
    {
        $roles = Role::query()
            ->with('permissions') // @pest-mutate-ignore (eager loading only saves queries)
            ->with(['employees' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('roles/Index', [
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
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
                ])
                ->all(),
            'employees' => Employee::query()
                ->orderBy('name')
                ->get(['id', 'name', 'start_date', 'leaving_date'])
                ->reject(fn (Employee $employee): bool => $employee->employmentStatus() === EmploymentStatus::Former)
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Create a Role, recorded in the Access Log.
     */
    public function store(#[CurrentUser] Employee $actor, RoleRequest $request, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->createRole($actor, $request->string('name')->value(), $request->grants(), $request->givenToNewEmployees());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    /**
     * Rename a Role or change its Permissions and Scopes.
     */
    public function update(#[CurrentUser] Employee $actor, RoleRequest $request, Role $role, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->editRole($actor, $role, $request->string('name')->value(), $request->grants(), $request->givenToNewEmployees());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role saved.')]);

        return to_route('roles.index');
    }

    /**
     * Delete a Role, taking it from everyone who holds it.
     */
    public function destroy(#[CurrentUser] Employee $actor, Role $role, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->deleteRole($actor, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
