<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\WorkEntryListRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\WorkEntryListing;
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
 * LockController; approving happens on the Approvals page and an
 * Employee's Work Entries tab.
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
        $requested = $request->filters();
        $visible = fn (): Builder => Employee::query()->withWorkEntriesVisibleTo($actor);
        $inPeriod = fn (Builder $entries): Builder => WorkEntryListing::inPeriod($entries, $requested['period'], $requested['state']);
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
        $projects = $employees === [] ? [] : WorkEntryListing::projects(
            $employee === 'all' ? $visible() : Employee::query()->whereKey($employee),
            $requested['period'],
            $requested['state'],
        );
        $filters = [
            'period' => $requested['period'],
            'employee' => $employee,
            'project' => collect($projects)->firstWhere('id', $requested['project'])['id'] ?? null,
            'state' => $requested['state'],
        ];
        $matching = WorkEntry::query()
            ->ofEmployees($visible())
            ->filteredBy([...$filters, 'employee' => is_int($employee) ? $employee : null]);

        return Inertia::render('work-entries/List', [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'canReopen' => $actor->holds(Permission::ReopenWorkEntries),
            'canLock' => $actor->can('lock', WorkEntry::class),
            ...(new WorkEntryListing($actor))->of($matching),
            'employees' => $employees,
            'projects' => $projects,
        ]);
    }
}
