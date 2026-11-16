<?php

namespace App\Http\Controllers\Access;

use App\Actions\ChangeAccess;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
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
     * The Roles page on the first Role by name.
     */
    public function index(): Response
    {
        return $this->page('view', Role::query()->orderBy('name')->orderBy('id')->first());
    }

    /**
     * The Roles page on one Role.
     */
    public function show(Role $role): Response
    {
        return $this->page('view', $role);
    }

    /**
     * The Roles page with a new Role being made.
     */
    public function create(): Response
    {
        return $this->page('create', null);
    }

    /**
     * The Roles page with one Role being edited.
     */
    public function edit(Role $role): Response
    {
        return $this->page('edit', $role);
    }

    /**
     * Create a Role, recorded in the Access Log, and show it.
     */
    public function store(#[CurrentUser] Employee $actor, RoleRequest $request, ChangeAccess $changeAccess): RedirectResponse
    {
        $role = $changeAccess->createRole($actor, $request->string('name')->value(), $request->grants(), $request->givenToNewEmployees(), $request->holders());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created', ['name' => $role->name])]);

        return to_route('roles.show', $role);
    }

    /**
     * Rename a Role, change its Permissions, Scopes and holders, and show
     * it again.
     */
    public function update(#[CurrentUser] Employee $actor, RoleRequest $request, Role $role, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->editRole($actor, $role, $request->string('name')->value(), $request->grants(), $request->givenToNewEmployees(), $request->holders());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role saved.')]);

        return to_route('roles.show', $role);
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

    /**
     * The Roles page: every Role, by name, with its Permissions and holders,
     * the one selected, and whether it is viewed, edited or a new one is
     * being made; plus every Permission a Role can contain, and every
     * Employee who hasn't left, to give Roles to.
     *
     * @param  'view'|'edit'|'create'  $mode
     */
    private function page(string $mode, ?Role $selected): Response
    {
        $roles = Role::query()
            ->with('permissions') // @pest-mutate-ignore (eager loading only saves queries)
            ->with(['employees' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Roles that share a name, which the unique index rules out)
            ->get();

        return Inertia::render('roles/Index', [
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
                ->orderBy('name')
                ->get(['id', 'name', 'start_date', 'leaving_date'])
                ->reject(fn (Employee $employee): bool => $employee->employmentStatus() === EmploymentStatus::Former)
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name, 'status' => $employee->employmentStatus()->value])
                ->values()
                ->all(),
        ]);
    }
}
