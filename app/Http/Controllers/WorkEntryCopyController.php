<?php

namespace App\Http\Controllers;

use App\Http\Requests\CopyWorkEntriesRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Repeats a typical day: copies an Employee's Work Entries from the latest
 * earlier day with any onto another day.
 */
class WorkEntryCopyController extends Controller
{
    /**
     * Copy the source day's Work Entries onto the day as new Pending ones,
     * all or none, then go back to the Period the page sent while it holds
     * the day.
     */
    public function store(CopyWorkEntriesRequest $request, Employee $employee): RedirectResponse
    {
        $date = $request->day();
        $toCopy = $request->toCopy();

        DB::transaction(fn () => $toCopy->each(fn (WorkEntry $entry) => WorkEntry::query()->create([
            'assignment_id' => $entry->assignment_id,
            'date' => $date->toDateString(),
            'minutes' => $entry->minutes,
            'description' => $entry->description,
        ])));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Copied :count Work Entry from :source.|[2,*] Copied :count Work Entries from :source.', $toCopy->count(), [
            'source' => $request->sourceLabel(),
        ])]);

        return to_route('work-entries.index', ['employee' => $employee->id, ...Period::holding($request->query(), $date)->query()]);
    }
}
