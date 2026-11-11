<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Where an Employee lands after signing in: their own Employee page, or the
 * directory if they can't open it, or else their profile settings, which
 * every Employee can reach.
 */
class LandingController extends Controller
{
    /**
     * Send the Employee to the first page they may see.
     */
    public function __invoke(#[CurrentUser] Employee $employee): RedirectResponse
    {
        if ($employee->can('view', $employee)) {
            return to_route('employees.show', $employee);
        }

        if ($employee->can(Permission::ViewDirectory->value)) {
            return to_route('directory.index');
        }

        return to_route('profile.edit');
    }
}
