<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\WorkEntryState;
use App\Http\Requests\WorkEntryListRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkEntry;
use App\Support\Period;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Work Entries page: Work Entries of the Employees the viewer may see,
 * one Period and one Employee at a time, or every Employee at once, with
 * reopening and locking many of them at once. Who is listed comes from the
 * viewer's view, manage and reopen Scopes, or everyone for someone who can
 * lock. Reopening is done by the ReopeningController and locking by the
 * LockController; approving happens on the Approvals page.
 */
class WorkEntryListController extends Controller
{
    /**
     * The Work Entries matching the filters, in every state unless one is
     * chosen, oldest first, with the total hours of those that aren't
     * Declined, and of those that are. The Employees to choose from are those the viewer may see
     * with Work Entries in the Period in the chosen state, by name with how
     * many; an Employee not among them, or
     * none, falls back to the first, while "all" keeps every Employee. The
     * Projects to choose from are those the chosen Employee, or anyone, has
     * Work Entries on during the Period; one not among them falls back to any
     * Project. With no Work Entries in the Period, there is nobody to choose.
     */
    public function index(#[CurrentUser] Employee $actor, WorkEntryListRequest $request): Response
    {
        $canLock = $actor->can('lock', WorkEntry::class);
        $requested = $request->filters();
        $visible = fn (): Builder => Employee::query()->withWorkEntriesVisibleTo($actor);
        $inPeriod = fn (Builder $entries): Builder => self::inPeriod($entries, $requested['period'], $requested['state']);
        $employees = $visible()
            ->whereHas('workEntries', $inPeriod)
            ->withCount(['workEntries as entries_count' => $inPeriod])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
            ->get(['id', 'name'])
            ->map(fn (Employee $employee): array => [...$employee->only('id', 'name'), 'count' => (int) $employee->getAttribute('entries_count')])
            ->all();
        $employee = $requested['employee'] === 'all'
            ? 'all'
            : collect($employees)->firstWhere('id', $requested['employee'])['id'] ?? $employees[0]['id'] ?? null;
        $projects = $employees === [] ? [] : Project::query()
            ->whereHas('workEntries', fn (Builder $entries): Builder => $inPeriod($entries)
                ->ofEmployees($employee === 'all' ? $visible() : Employee::query()->whereKey($employee)))
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Projects that share a name)
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => $project->only('id', 'name'))
            ->all();
        $filters = [
            'period' => $requested['period'],
            'employee' => $employee,
            'project' => collect($projects)->firstWhere('id', $requested['project'])['id'] ?? null,
            'state' => $requested['state'],
        ];
        $matching = WorkEntry::query()
            ->ofEmployees($visible())
            ->filteredBy([...$filters, 'employee' => is_int($employee) ? $employee : null]);
        $reopenable = Employee::query()->reopenableBy($actor)->pluck('id');

        return Inertia::render('work-entries/List', [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'canReopen' => $actor->holds(Permission::ReopenWorkEntries),
            'canLock' => $canLock,
            'entries' => (clone $matching)
                ->with(['assignment.employee:id,name', 'assignment.project:id,name', 'approver:id,name', 'locker:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
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
                    'declined' => $entry->decline(),
                    'can_reopen' => $entry->state === WorkEntryState::Approved && $reopenable->contains($entry->assignment->employee_id),
                    'can_lock' => $entry->state === WorkEntryState::Approved && $canLock,
                ]),
            'total' => (int) (clone $matching)->counted()->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'declinedTotal' => (int) (clone $matching)->where('state', WorkEntryState::Declined)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $employees,
            'projects' => $projects,
        ]);
    }

    /**
     * Narrows Work Entries to those in the Period, and in the state, or any
     * state when none is given.
     *
     * @param  Builder<WorkEntry>  $entries
     * @return Builder<WorkEntry>
     */
    private static function inPeriod(Builder $entries, Period $period, ?string $state): Builder
    {
        return $entries->filteredBy(['period' => $period, 'state' => $state]);
    }
}
