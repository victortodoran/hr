<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\ChangeAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Pages\Access\RolesPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('roles/Index', RolesPage::index());
    }

    public function show(Role $role): Response
    {
        return Inertia::render('roles/Index', RolesPage::show($role));
    }

    public function create(): Response
    {
        return Inertia::render('roles/Index', RolesPage::create());
    }

    public function store(#[CurrentUser] Employee $actor, RoleRequest $request, ChangeAccess $changeAccess): RedirectResponse
    {
        $role = $changeAccess->createRole($actor, $request->string('name')->value(), $request->grants(), $request->givenToNewEmployees(), $request->holders());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created', ['name' => $role->name])]);

        return to_route('roles.show', $role);
    }

    public function update(#[CurrentUser] Employee $actor, RoleRequest $request, Role $role, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->editRole($actor, $role, $request->string('name')->value(), $request->grants(), $request->givenToNewEmployees(), $request->holders());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role saved.')]);

        return to_route('roles.show', $role);
    }

    public function destroy(#[CurrentUser] Employee $actor, Role $role, ChangeAccess $changeAccess): RedirectResponse
    {
        $changeAccess->deleteRole($actor, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
