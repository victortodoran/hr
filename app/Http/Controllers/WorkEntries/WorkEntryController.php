<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\WorkEntries\CorrectWorkEntry;
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

class WorkEntryController extends Controller
{
    /** An Employee who doesn't exist is refused like one outside the viewer's Scope, so the page never reveals who exists. */
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

    public function store(WorkEntryRequest $request, Employee $employee): RedirectResponse
    {
        $workEntry = WorkEntry::query()->create($request->workEntry());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry added.')]);

        return $this->backTo($request, $employee, $workEntry);
    }

    public function update(WorkEntryRequest $request, WorkEntry $workEntry, CorrectWorkEntry $correctWorkEntry): RedirectResponse
    {
        $changed = $correctWorkEntry->correct($workEntry, $request->workEntry());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Work Entry is approved between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry changed.')]);

        return $this->backTo($request, $request->employee(), $workEntry);
    }

    public function destroy(Request $request, WorkEntry $workEntry): RedirectResponse
    {
        $workEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry deleted.')]);

        return $this->backTo($request, $workEntry->employee, $workEntry);
    }

    private function backTo(Request $request, Employee $employee, WorkEntry $workEntry): RedirectResponse
    {
        return to_route('work-entries.index', ['employee' => $employee->id, ...WorkEntriesTab::backTo($request->query(), $workEntry->date)]);
    }
}
