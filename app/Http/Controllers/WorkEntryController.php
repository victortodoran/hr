<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntriesTab;
use App\Http\Requests\WorkEntryPeriodRequest;
use App\Http\Requests\WorkEntryRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
            ->with('assignment.project:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('work_entries.date')
            ->orderBy('work_entries.id')
            ->get();
        $timeOff = $employee->timeOff()->standing()->overlapping($period->from, $period->to)->get();

        $canManage = $actor->can('create', [WorkEntry::class, $employee]);
        $canDelete = $actor->can(Permission::ManageWorkEntries->value, $employee);
        $copySources = $canManage ? $this->datesWithEntries($employee, $period, $entries) : collect();
        $days = $this->days($employee, $period, $entries, $timeOff, $canManage, $canDelete, $copySources);

        $tab = $request->tab();

        return Inertia::render('work-entries/Index', [
            'tab' => $tab->value,
            'employee' => $employee->only('id', 'name'),
            'period' => $period->toArray(),
            'today' => today()->toDateString(),
            'total' => $entries->sum('hours'),
            'assignmentTotals' => $this->assignmentTotals($entries),
            'assignments' => $this->assignments($employee, $period),
            'canManage' => $canManage,
            'isOwn' => $employee->is($actor),
            ...match ($tab) {
                WorkEntriesTab::List => ['days' => $days, 'weeks' => $this->weeks($days)],
                WorkEntriesTab::Calendar => ['calendar' => $this->calendar($days)],
            },
        ]);
    }

    /**
     * The Employee's Assignments that run during the Period, by Project
     * name, for adding and changing Work Entries.
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
     * The Calendar tab: the month's ISO weeks, each with its number, its
     * hours, and its seven days from Monday to Sunday, null for a day of
     * another month. Each day is the List's day, so it can be added to,
     * copied onto and have Work Entries deleted from the Calendar.
     *
     * @param  array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int, time_off: string|null, nothing_logged: bool, copy_from: string|null}>  $days
     * @return list<array{number: int, hours: float|int, days: list<array<string, mixed>|null>}>
     */
    private function calendar(array $days): array
    {
        $byDate = collect($days)->keyBy('date');

        return array_map(function (array $week) use ($byDate): array {
            $monday = CarbonImmutable::parse($week['from'])->startOfWeek(CarbonInterface::MONDAY);

            return [
                'number' => $week['number'],
                'hours' => $week['hours'],
                'days' => array_map(fn (int $offset): ?array => $byDate->get($monday->addDays($offset)->toDateString()), range(0, 6)),
            ];
        }, $this->weeks($days));
    }

    /**
     * Every day of the Period, each with its Work Entries and their total. Each
     * Work Entry carries its state, and whether
     * the viewer can change or delete it: both need a Pending Work Entry,
     * changing needs what recording does, and deleting needs only manage
     * work entries for the Employee, the top Employee included.
     *
     * A weekday covered by Pending or Approved Time Off says whether that
     * Time Off is Paid or Unpaid. A past weekday while the Employee works
     * here, with neither Work Entries nor such Time Off, is nothing logged.
     * A day up to today with no Work Entries and no such Time Off, when the
     * viewer can record hours, names the day copying Work Entries onto it
     * would copy from: the latest day in the week before with any, or null.
     *
     * @param  Collection<int, WorkEntry>  $entries
     * @param  Collection<int, TimeOff>  $timeOff  the Employee's Pending and Approved Time Off overlapping the Period
     * @param  SupportCollection<int, string>  $copySources  the dates, in order, the Employee has Work Entries on from a week before the Period
     * @return array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int, time_off: string|null, nothing_logged: bool, copy_from: string|null}>
     */
    private function days(Employee $employee, Period $period, Collection $entries, Collection $timeOff, bool $canManage, bool $canDelete, SupportCollection $copySources): array
    {
        return collect($period->days())->map(function (CarbonImmutable $day) use ($employee, $entries, $timeOff, $canManage, $canDelete, $copySources): array {
            $date = $day->toDateString();
            $onDay = $entries->filter(fn (WorkEntry $entry): bool => $entry->date->toDateString() === $date);
            $away = $day->isWeekday()
                ? $timeOff->first(fn (TimeOff $covering): bool => $day->between($covering->first_date, $covering->last_date))
                : null;
            $employed = ! $day->isBefore($employee->start_date)
                && ($employee->leaving_date === null || ! $day->isAfter($employee->leaving_date));

            return [
                'date' => $date,
                'entries' => $onDay->map(fn (WorkEntry $entry): array => [
                    'id' => $entry->id,
                    'assignment_id' => $entry->assignment_id,
                    'project' => $entry->assignment->project->name,
                    'hours' => $entry->hours,
                    'description' => $entry->description,
                    'state' => $entry->state->value,
                    'can_edit' => $canManage && $entry->isPending(),
                    'can_delete' => $canDelete && $entry->isPending(),
                ])->values()->all(),
                'hours' => $onDay->sum('hours'),
                'time_off' => $away === null ? null : ($away->is_paid ? 'paid' : 'unpaid'),
                'nothing_logged' => $day->isWeekday() && $day->isBefore(today()) && $employed && $onDay->isEmpty() && $away === null,
                'copy_from' => $canManage && ! $day->isAfter(today()) && $away === null && $onDay->isEmpty()
                    ? $copySources->last(fn (string $source): bool => $source < $date && $source >= $day->subDays(WorkEntry::COPY_LOOKBACK_DAYS)->toDateString())
                    : null,
            ];
        })->all();
    }

    /**
     * The dates, in order, the Employee has Work Entries on from a week
     * before the Period to its end: those in the week before are looked up,
     * those within it come from its Work Entries.
     *
     * @param  Collection<int, WorkEntry>  $entries  the Employee's Work Entries in the Period
     * @return SupportCollection<int, string>
     */
    private function datesWithEntries(Employee $employee, Period $period, Collection $entries): SupportCollection
    {
        $before = $employee->workEntries()
            ->whereDate('work_entries.date', '>=', $period->from->subDays(WorkEntry::COPY_LOOKBACK_DAYS))
            ->whereDate('work_entries.date', '<', $period->from)
            ->get(['work_entries.date']);

        return $before->toBase()
            ->merge($entries)
            ->map(fn (WorkEntry $entry): string => $entry->date->toDateString())
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * The Period's days split into ISO weeks, Monday to Sunday, each with
     * its number and the first and last of its days that fall within the
     * Period, and their hours.
     *
     * @param  array<int, array{date: string, hours: float|int}>  $days
     * @return list<array{number: int, from: string, to: string, hours: float|int}>
     */
    private function weeks(array $days): array
    {
        $weeks = [];

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day['date']);
            $monday = $date->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $weeks[$monday] ??= ['number' => $date->isoWeek, 'from' => $day['date'], 'to' => $day['date'], 'hours' => 0];
            $weeks[$monday]['to'] = $day['date'];
            $weeks[$monday]['hours'] += $day['hours'];
        }

        return array_values($weeks);
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
     * Correct a Work Entry: its Assignment, date, hours or description.
     */
    public function update(WorkEntryRequest $request, WorkEntry $workEntry): RedirectResponse
    {
        $workEntry->update($request->workEntry());

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
