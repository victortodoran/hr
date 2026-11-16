<?php

namespace App\Http\Controllers\WorkEntries;

use App\Enums\WorkEntryState;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\SelectedWorkEntriesRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Reopening Work Entries from the Work Entries page: sending Approved ones
 * back to Pending so they can be corrected. Only a Role that grants reopening
 * reopens them; being the Manager is not enough, and nobody reopens their
 * own.
 */
class ReopeningController extends Controller
{
    /**
     * Reopen the selected Work Entries the actor may reopen: Approved ones of
     * Employees their reopen Scope covers, never their own, sent back to
     * Pending with their approval withdrawn. The rest are skipped, and the
     * toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $reopened = WorkEntry::query()
            ->ofEmployees(Employee::query()->reopenableBy($actor))
            ->whereKey($ids)
            ->where('state', WorkEntryState::Approved)
            ->update(['state' => WorkEntryState::Pending, 'approved_by' => null, 'approved_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries reopened|{1} 1 Work Entry reopened|[2,*] :count Work Entries reopened', $reopened),
            count($ids) - $reopened,
        )]);

        return back();
    }
}
