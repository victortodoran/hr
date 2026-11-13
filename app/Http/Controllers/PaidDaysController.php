<?php

namespace App\Http\Controllers;

use App\Enums\EmployeePageTab;
use App\Http\Requests\PaidDaysRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets those who manage Paid Days set an Employee's Paid Days: one number
 * that applies to every year, past ones included.
 */
class PaidDaysController extends Controller
{
    /**
     * Set the Employee's Paid Days.
     */
    public function store(PaidDaysRequest $request, Employee $employee): RedirectResponse
    {
        $employee->paid_days = $request->integer('days');
        $employee->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paid Days saved.')]);

        return to_route('employees.show', ['employee' => $employee, 'tab' => EmployeePageTab::TimeOff]);
    }
}
