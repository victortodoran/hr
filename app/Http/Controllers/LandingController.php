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
        return match (true) { // @pest-mutate-ignore (coverage never attributes this line to a test, like class attributes)
            $employee->can('view', $employee) => to_route('employees.show', $employee),
            $employee->can(Permission::ViewDirectory->value) => to_route('directory.index'),
            default => to_route('profile.edit'),
        };
    }
}
