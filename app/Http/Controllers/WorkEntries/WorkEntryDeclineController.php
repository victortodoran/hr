<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\WorkEntries\DeclineWorkEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\DeclineWorkEntriesRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an effective Manager decline Pending Work Entries from the Approvals
 * page, several at once with one reason.
 */
class WorkEntryDeclineController extends Controller
{
    /**
     * Decline the selected Work Entries the actor may decline, the same ones
     * they may approve: Pending ones of Employees whose effective Manager
     * they are. Each records who declined it, when and why. The rest are
     * skipped, and the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, DeclineWorkEntriesRequest $request, DeclineWorkEntries $declineWorkEntries): RedirectResponse
    {
        $ids = $request->ids();

        $declined = $declineWorkEntries->decline($actor, $ids, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries declined|{1} 1 Work Entry declined|[2,*] :count Work Entries declined', $declined),
            count($ids) - $declined,
        )]);

        return back();
    }
}
