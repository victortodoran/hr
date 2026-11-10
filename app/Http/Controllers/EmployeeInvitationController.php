<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets HR resend an invitation to an Employee who has yet to set a password.
 */
class EmployeeInvitationController extends Controller
{
    /**
     * Send a fresh invitation; the previous link stops working.
     */
    public function store(Employee $employee): RedirectResponse
    {
        $employee->sendInvitation();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent again to :email.', ['email' => $employee->email])]);

        return to_route('employees.edit', $employee);
    }
}
