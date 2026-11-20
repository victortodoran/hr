<?php

namespace App\Http\Controllers\TimeOff;

use App\Enums\EmployeePageTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeOff\PaidDaysRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PaidDaysController extends Controller
{
    public function store(PaidDaysRequest $request, Employee $employee): RedirectResponse
    {
        $employee->paid_days = $request->integer('days');
        $employee->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paid Days saved.')]);

        return to_route('employees.show', ['employee' => $employee, 'tab' => EmployeePageTab::TimeOff]);
    }
}
