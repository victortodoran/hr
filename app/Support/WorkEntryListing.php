<?php

namespace App\Support;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkEntry;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\OptionalProp;

/**
 * Work Entries listed for review, as the Work Entries page and an
 * Employee's Work Entries tab show them: a page of them oldest first, each
 * with who approved, locked or declined it and what the viewer may do to it,
 * with the hours that count and the Declined hours that don't.
 */
final readonly class WorkEntryListing
{
    public function __construct(private Employee $viewer) {}

    /**
     * Narrows Work Entries to those in the Period, and in the state, or any
     * state when none is given.
     *
     * @param  Builder<WorkEntry>  $entries
     * @return Builder<WorkEntry>
     */
    public static function inPeriod(Builder $entries, Period $period, ?string $state): Builder
    {
        return $entries->filteredBy(['period' => $period, 'state' => $state]);
    }

    /**
     * The Projects the given Employees have Work Entries on in the Period, in
     * the state or any, by name.
     *
     * @param  Builder<Employee>  $employees
     * @return array<int, array{id: int, name: string}>
     */
    public static function projects(Builder $employees, Period $period, ?string $state): array
    {
        return Project::query()
            ->whereHas('workEntries', fn (Builder $entries): Builder => self::inPeriod($entries, $period, $state)->ofEmployees($employees))
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Projects that share a name)
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => $project->only('id', 'name'))
            ->all();
    }

    /**
     * The matching Work Entries, 25 to a page, each saying whether the viewer
     * may reopen it (Approved, and in their reopen Scope) or lock it
     * (Approved, and they can lock), and, when deciding is offered, approve
     * or decline it (Pending, and theirs to decide as the Employee's
     * effective Manager); the total hours of
     * those that aren't Declined and of those that are; and, only when asked
     * for, every matching Work Entry's ID.
     *
     * @param  Builder<WorkEntry>  $matching
     * @return array{entries: mixed, total: int|float, declinedTotal: int|float, matchingIds: OptionalProp}
     */
    public function of(Builder $matching, bool $withDeciding = false): array
    {
        $canLock = $this->viewer->can('lock', WorkEntry::class);
        $page = (clone $matching)
            ->with(['assignment.employee:id,name', 'assignment.project:id,name', 'approver:id,name', 'locker:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('date')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
        $onPage = $page->getCollection()->pluck('assignment.employee_id')->unique()->values()->all();
        $reopenable = Employee::query()->reopenableBy($this->viewer)->whereKey($onPage)->pluck('id');
        $decidable = $withDeciding ? Employee::query()->effectivelyManagedBy($this->viewer)->whereKey($onPage)->pluck('id') : null;

        return [
            'entries' => $page
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
                    ...($decidable === null ? [] : ['can_decide' => $entry->state === WorkEntryState::Pending && $decidable->contains($entry->assignment->employee_id)]),
                ]),
            'total' => (int) (clone $matching)->counted()->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'declinedTotal' => (int) (clone $matching)->where('state', WorkEntryState::Declined)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
        ];
    }
}
