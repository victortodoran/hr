<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmployeeInvitationController extends Controller
{
    public function store(Employee $employee): RedirectResponse
    {
        $employee->sendInvitation();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent again to :email.', ['email' => $employee->email])]);

        return to_route('employees.edit', $employee);
    }
}
