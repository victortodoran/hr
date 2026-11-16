<?php

namespace App\Http\Controllers\WorkEntries;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntriesTab;
use App\Enums\WorkEntryState;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryPeriodRequest;
use App\Http\Requests\WorkEntries\WorkEntryRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\Period;
use App\Support\WorkEntryCalendar;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
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
        $employee = $request->filled('employee') ? Employee::query()->find($request->integer('employee')) : $actor;
        abort_if($employee === null, 403);
        Gate::authorize('viewAny', [WorkEntry::class, $employee]);
        $period = $request->period();

        $entries = $employee->workEntries()
            ->whereBetween('work_entries.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->with(['assignment.project:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('work_entries.date')
            ->orderBy('work_entries.id')
            ->get();
        $declined = $entries->filter(fn (WorkEntry $entry): bool => $entry->isDeclined());
        $counted = $entries->diff($declined);
        $timeOff = $employee->timeOff()->standing()->overlapping($period->from, $period->to)->get();

        $canManage = $actor->can('create', [WorkEntry::class, $employee]);
        $canDelete = $actor->can(Permission::ManageWorkEntries->value, $employee);
        $calendar = WorkEntryCalendar::of($employee, $period, $entries, $timeOff, $canManage, $canDelete);

        $tab = $request->tab();

        return Inertia::render('work-entries/Index', [
            'tab' => $tab->value,
            'employee' => $employee->only('id', 'name'),
            'period' => $period->toArray(),
            'today' => today()->toDateString(),
            'total' => $counted->sum('hours'),
            'declinedHours' => $declined->sum('hours'),
            'assignmentTotals' => $this->assignmentTotals($counted),
            'assignments' => $canManage ? $this->assignments($employee, $period) : [],
            'canManage' => $canManage,
            'isOwn' => $employee->is($actor),
            ...match ($tab) {
                WorkEntriesTab::List => ['days' => $calendar->days(), 'weeks' => $calendar->weeks()],
                WorkEntriesTab::Calendar => ['calendar' => $calendar->calendar()],
            },
        ]);
    }

    /**
     * The Employee's Assignments that run during the Period, by Project
     * name, for adding and changing Work Entries; only sent to a viewer who
     * can record the Employee's hours.
     *
     * @return SupportCollection<int, array{id: int, project: string, job_title: string|null, start_date: string, end_date: string|null}>
     */
    private function assignments(Employee $employee, Period $period): SupportCollection
    {
        return $employee->assignments()
            ->whereDate('start_date', '<=', $period->to)
            ->where(fn (Builder $running) => $running->whereNull('end_date')->orWhereDate('end_date', '>=', $period->from))
            ->with('project:id,name')
            ->get()
            ->sortBy('project.name')
            ->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'project' => $assignment->project->name,
                'job_title' => $assignment->job_title,
                'start_date' => $assignment->start_date->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
            ])
            ->values()
            ->toBase();
    }

    /**
     * The hours on each Assignment in the Period, most hours first, then by
     * Project name.
     *
     * @param  Collection<int, WorkEntry>  $entries
     * @return SupportCollection<int, array{assignment_id: int, project: string, hours: float|int}>
     */
    private function assignmentTotals(Collection $entries): SupportCollection
    {
        return $entries->groupBy('assignment_id')
            ->map(fn (Collection $onAssignment): array => [
                'assignment_id' => $onAssignment->firstOrFail()->assignment_id,
                'project' => $onAssignment->firstOrFail()->assignment->project->name,
                'hours' => $onAssignment->sum(fn (WorkEntry $entry): float|int => $entry->hours),
            ])
            ->sortBy([['hours', 'desc'], ['project', 'asc']])
            ->values();
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
     * The write only changes one still Pending or Declined, so an approval
     * made since the policy check is never undone.
     */
    public function update(WorkEntryRequest $request, WorkEntry $workEntry): RedirectResponse
    {
        $changed = WorkEntry::query()
            ->whereKey($workEntry->id)
            ->whereIn('state', [WorkEntryState::Pending, WorkEntryState::Declined])
            ->update([
                ...$request->workEntry(),
                'state' => WorkEntryState::Pending,
                'declined_by' => null,
                'declined_at' => null,
                'decline_reason' => null,
                'updated_at' => now(),
            ]);
        abort_if($changed === 0, 403); // @pest-mutate-ignore (only reached when the Work Entry is approved between the policy check and this write)
        $workEntry->refresh();

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
