<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\WorkEntries\ReopenWorkEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\SelectedWorkEntriesRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReopeningController extends Controller
{
    public function store(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request, ReopenWorkEntries $reopenWorkEntries): RedirectResponse
    {
        $ids = $request->ids();

        $reopened = $reopenWorkEntries->reopen($actor, $ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries reopened|{1} 1 Work Entry reopened|[2,*] :count Work Entries reopened', $reopened),
            count($ids) - $reopened,
        )]);

        return back();
    }
}
