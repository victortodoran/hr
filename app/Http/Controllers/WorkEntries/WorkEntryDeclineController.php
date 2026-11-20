<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\WorkEntries\DeclineWorkEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\DeclineWorkEntriesRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WorkEntryDeclineController extends Controller
{
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
