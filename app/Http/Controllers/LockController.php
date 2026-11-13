<?php

namespace App\Http\Controllers;

use App\Enums\WorkEntryState;
use App\Http\Requests\LockPreviewRequest;
use App\Http\Requests\SelectedWorkEntriesRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Locking Work Entries for good from the Work Entries page. Locking applies
 * company-wide, the locker's own Work Entries included, and only ever to
 * Approved ones (ADR 0005).
 */
class LockController extends Controller
{
    /**
     * What locking the selected Work Entries would do, for the confirmation:
     * how many are still Approved and their hours, broken down by Project and
     * Employee, and how many Pending Work Entries match the same filters, so
     * the locker can wait for them instead.
     */
    public function create(LockPreviewRequest $request): JsonResponse
    {
        $locking = WorkEntry::query()
            ->whereKey($request->ids())
            ->where('state', WorkEntryState::Approved)
            ->with(['assignment.employee:id,name', 'assignment.project:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same summary)
            ->get();
        $pending = WorkEntry::query()->filteredBy([...$request->filters(), 'state' => WorkEntryState::Pending->value]);
        $byProjectThenEmployee = [
            fn (array $one, array $other): int => $one['project'] <=> $other['project'],
            fn (array $one, array $other): int => $one['employee'] <=> $other['employee'],
        ];

        return response()->json([
            'locking' => ['count' => $locking->count(), 'hours' => $locking->sum('minutes') / 60],
            'breakdown' => $locking
                ->groupBy(fn (WorkEntry $entry): string => $entry->assignment->project->name."\0".$entry->assignment->employee->name) // @pest-mutate-ignore (any key joining the Project and Employee names groups the same)
                ->map(fn (Collection $group): array => [
                    'project' => $group->firstOrFail()->assignment->project->name,
                    'employee' => $group->firstOrFail()->assignment->employee->name,
                    'hours' => $group->sum('minutes') / 60,
                ])
                ->sortBy($byProjectThenEmployee)
                ->values(),
            'pending' => ['count' => (clone $pending)->count(), 'hours' => (int) $pending->sum('minutes') / 60], // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
        ]);
    }

    /**
     * Lock the selected Work Entries that are still Approved, recording who
     * locked them and when. Anything else, such as one reopened since the
     * locker saw it, is skipped, and the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $locked = WorkEntry::query()
            ->whereKey($ids)
            ->where('state', WorkEntryState::Approved)
            ->update(['state' => WorkEntryState::Locked, 'locked_by' => $actor->id, 'locked_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries locked|{1} 1 Work Entry locked|[2,*] :count Work Entries locked', $locked),
            count($ids) - $locked,
        )]);

        return back();
    }
}
