<?php

namespace App\Http\Controllers\Access;

use App\Actions\ChangeAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\GiveRoleRequest;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets Employees who manage Roles give Roles to Employees and take them
 * away, including from themselves.
 */
class RoleHolderController extends Controller
{
    /**
     * Give the Role to Employees who haven't left and don't hold it yet,
     * all together.
     */
    public function store(#[CurrentUser] Employee $actor, GiveRoleRequest $request, Role $role, ChangeAccess $changeAccess): RedirectResponse
    {
        $employees = $request->employees();

        $changeAccess->giveRoleToEach($actor, $role, $employees);

        Inertia::flash('toast', ['type' => 'success', 'message' => $employees->count() === 1
            ? __(':name added to :role.', ['role' => $role->name, 'name' => $employees->sole()->name])
            : __(':count people added to :role.', ['role' => $role->name, 'count' => $employees->count()])]);

        return to_route('roles.show', $role);
    }

    /**
     * Take the Role away from an Employee who holds it.
     */
    public function destroy(#[CurrentUser] Employee $actor, Role $role, Employee $employee, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->takeRole($actor, $role, $employee);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name removed from :role.', ['role' => $role->name, 'name' => $employee->name])]);

        return to_route('roles.show', $role);
    }
}
