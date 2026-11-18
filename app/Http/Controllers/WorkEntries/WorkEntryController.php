<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\CorrectWorkEntry;
use App\Enums\Permission;
use App\Enums\WorkEntriesTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryPeriodRequest;
use App\Http\Requests\WorkEntries\WorkEntryRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Pages\WorkEntries\WeekPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The week of Work Entries for one Employee, and recording, correcting and
 * deleting them.
 */
class WorkEntryController extends Controller
{
    /**
     * One Employee's Work Entries for one Period, a week from Monday to
     * Sunday, a month or a custom period, day by day, with totals for each
     * day, each Assignment and the Period. Opens on the viewer's own current
     * week; another Employee's is reached only by a link, such as from the
     * Work Entries page. An Employee who doesn't exist is refused just like
     * one outside the viewer's Scope, so the page never reveals who exists.
     */
    public function index(#[CurrentUser] Employee $actor, WorkEntryPeriodRequest $request): Response
    {
        $employee = $request->employee($actor);
        abort_if($employee === null, 403);
        Gate::authorize('viewAny', [WorkEntry::class, $employee]);

        return Inertia::render('work-entries/Index', WeekPage::props(
            $actor,
            $employee,
            $request->period(),
            $request->tab(),
            canManage: $actor->can('create', [WorkEntry::class, $employee]),
            canDelete: $actor->can(Permission::ManageWorkEntries->value, $employee),
        ));
    }

    /**
     * Record hours for the Employee.
     */
    public function store(WorkEntryRequest $request, Employee $employee): RedirectResponse
    {
        $workEntry = WorkEntry::query()->create($request->workEntry());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry added.')]);

        return $this->backTo($request, $employee, $workEntry);
    }

    /**
     * Correct a Work Entry: its Assignment, date, hours or description. A
     * Declined one goes back to Pending, without who declined it or why.
     * One approved since the policy check is refused, never changed.
     */
    public function update(WorkEntryRequest $request, WorkEntry $workEntry, CorrectWorkEntry $correctWorkEntry): RedirectResponse
    {
        $changed = $correctWorkEntry->correct($workEntry, $request->workEntry());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Work Entry is approved between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry changed.')]);

        return $this->backTo($request, $request->employee(), $workEntry);
    }

    /**
     * Delete a Work Entry recorded by mistake.
     */
    public function destroy(Request $request, WorkEntry $workEntry): RedirectResponse
    {
        $workEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry deleted.')]);

        return $this->backTo($request, $workEntry->employee, $workEntry);
    }

    /**
     * Back to the tab and Period the page sent in its query, holding the
     * Work Entry's day (see WorkEntriesTab::backTo).
     */
    private function backTo(Request $request, Employee $employee, WorkEntry $workEntry): RedirectResponse
    {
        return to_route('work-entries.index', ['employee' => $employee->id, ...WorkEntriesTab::backTo($request->query(), $workEntry->date)]);
    }
}
