<?php

namespace App\Http\Controllers;

use App\Actions\NameTopEmployee;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets those who manage the company hand the top spot to another Employee.
 */
class TopEmployeeController extends Controller
{
    /**
     * Make the Employee the new top Employee.
     */
    public function store(Employee $employee, NameTopEmployee $nameTopEmployee): RedirectResponse
    {
        $nameTopEmployee->handOverTo($employee);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now the top Employee.', ['name' => $employee->name])]);

        return to_route('employees.edit', $employee);
    }
}
