<?php

namespace App\Http\Controllers\Access;

use App\Actions\ChangeAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Pages\Access\RolesPage;
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
        return Inertia::render('roles/Index', RolesPage::index());
    }

    /**
     * The Roles page on one Role, editable in place.
     */
    public function show(Role $role): Response
    {
        return Inertia::render('roles/Index', RolesPage::show($role));
    }

    /**
     * The Roles page with a new Role being made.
     */
    public function create(): Response
    {
        return Inertia::render('roles/Index', RolesPage::create());
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
}
