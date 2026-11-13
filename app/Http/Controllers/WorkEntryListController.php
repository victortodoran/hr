<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\WorkEntryState;
use App\Http\Requests\WorkEntryListRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Work Entries page: Work Entries across the Employees the viewer may
 * see, filtered, with reopening and locking many of them at once. Who is
 * listed comes from the viewer's view, manage and reopen Scopes, or everyone
 * for someone who can lock. Reopening is done by the ReopeningController and
 * locking by the LockController; approving happens on the Approvals page.
 */
class WorkEntryListController extends Controller
{
    /**
     * The Work Entries matching the filters, in every state unless one is
     * chosen, oldest first, with their total hours.
     */
    public function index(#[CurrentUser] Employee $actor, WorkEntryListRequest $request): Response
    {
        $canLock = $actor->can('lock', WorkEntry::class);
        $filters = $request->filters();
        $matching = WorkEntry::query()
            ->ofEmployees(Employee::query()->withWorkEntriesVisibleTo($actor))
            ->filteredBy($filters);
        $reopenable = Employee::query()->reopenableBy($actor)->pluck('id');

        return Inertia::render('work-entries/List', [
            'filters' => $filters,
            'canReopen' => $actor->holds(Permission::ReopenWorkEntries),
            'canLock' => $canLock,
            'entries' => (clone $matching)
                ->with(['assignment.employee:id,name', 'assignment.project:id,name', 'approver:id,name', 'locker:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('date')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (WorkEntry $entry): array => [
                    'id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'employee' => $entry->assignment->employee->only('id', 'name'),
                    'project' => $entry->assignment->project->name,
                    'hours' => $entry->hours,
                    'description' => $entry->description,
                    'state' => $entry->state->value,
                    'approved' => $entry->approval(),
                    'locked' => $entry->lock(),
                    'can_reopen' => $entry->state === WorkEntryState::Approved && $reopenable->contains($entry->assignment->employee_id),
                    'can_lock' => $entry->state === WorkEntryState::Approved && $canLock,
                ]),
            'total' => (int) (clone $matching)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => Employee::query()
                ->withWorkEntriesVisibleTo($actor)
                ->orderBy('name')
                ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
                ->get(['id', 'name'])
                ->map(fn (Employee $employee): array => $employee->only('id', 'name'))
                ->all(),
            'projects' => Project::choices(),
        ]);
    }
}
