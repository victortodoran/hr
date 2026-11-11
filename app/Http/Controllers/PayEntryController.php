<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayEntryRequest;
use App\Models\Assignment;
use App\Models\PayEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets those who manage Pay add entries to an Assignment's Pay history,
 * including raises ahead of time, and remove one entered by mistake.
 */
class PayEntryController extends Controller
{
    /**
     * Add an entry to the Assignment's Pay history.
     */
    public function store(PayEntryRequest $request, Assignment $assignment): RedirectResponse
    {
        $assignment->payEntries()->create($request->payEntry());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay added.')]);

        return to_route('employees.assignments.index', $assignment->employee_id);
    }

    /**
     * Remove an entry from the Assignment's Pay history.
     */
    public function destroy(Assignment $assignment, PayEntry $payEntry): RedirectResponse
    {
        $payEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay removed.')]);

        return to_route('employees.assignments.index', $assignment->employee_id);
    }
}
