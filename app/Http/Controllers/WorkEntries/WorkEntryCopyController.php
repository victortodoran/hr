<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\WorkEntries\CopyWorkEntries;
use App\Enums\WorkEntriesTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\CopyWorkEntriesRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WorkEntryCopyController extends Controller
{
    public function store(CopyWorkEntriesRequest $request, Employee $employee, CopyWorkEntries $copyWorkEntries): RedirectResponse
    {
        $date = $request->day();
        $toCopy = $request->toCopy();

        $copyWorkEntries->copy($toCopy, $date);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Copied :count Work Entry from :source.|[2,*] Copied :count Work Entries from :source.', $toCopy->count(), [
            'source' => $request->sourceLabel(),
        ])]);

        return to_route('work-entries.index', ['employee' => $employee->id, ...WorkEntriesTab::backTo($request->query(), $date)]);
    }
}
