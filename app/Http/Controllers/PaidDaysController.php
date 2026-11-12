<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaidDaysRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets those who manage Paid Days set an Employee's Paid Days for a year,
 * leaving every other year as it is.
 */
class PaidDaysController extends Controller
{
    /**
     * Set the Employee's Paid Days for one year.
     */
    public function store(PaidDaysRequest $request, Employee $employee): RedirectResponse
    {
        $year = $request->integer('year');

        $employee->paidDays()->updateOrCreate(['year' => $year], ['days' => $request->integer('days')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paid Days for :year saved.', ['year' => $year])]);

        return to_route('employees.show', $employee);
    }
}
