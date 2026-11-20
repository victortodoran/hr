<?php

namespace App\Http\Controllers\Assignments;

use App\Enums\EmployeePageTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\PayEntryRequest;
use App\Models\Assignment;
use App\Models\PayEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PayEntryController extends Controller
{
    public function store(PayEntryRequest $request, Assignment $assignment): RedirectResponse
    {
        $assignment->payEntries()->create($request->payEntry());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay added.')]);

        return to_route('employees.show', ['employee' => $assignment->employee_id, 'tab' => EmployeePageTab::Assignments]);
    }

    public function destroy(Assignment $assignment, PayEntry $payEntry): RedirectResponse
    {
        $payEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay removed.')]);

        return to_route('employees.show', ['employee' => $assignment->employee_id, 'tab' => EmployeePageTab::Assignments]);
    }
}
