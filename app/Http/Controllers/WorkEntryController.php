<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Scope;
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
            ->with(['assignment.project:id,name', 'approver:id,name', 'locker:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('work_entries.date')
            ->orderBy('work_entries.id')
            ->get();
        $timeOff = $employee->timeOff()->standing()->overlapping($period->from, $period->to)->get();

        $canManage = $actor->can('create', [WorkEntry::class, $employee]);
        $canDelete = $actor->can(Permission::ManageWorkEntries->value, $employee);
        $days = $this->days($employee, $period, $entries, $timeOff, $canManage, $canDelete);

        return Inertia::render('work-entries/Index', [
            'employee' => $employee->only('id', 'name'),
            'period' => $period->toArray(),
            'today' => today()->toDateString(),
            'days' => $days,
            'weeks' => $this->weeks($days),
            'assignmentTotals' => $entries->groupBy('assignment_id')
                ->map(fn (Collection $onAssignment): array => [
                    'assignment_id' => $onAssignment->firstOrFail()->assignment_id,
                    'project' => $onAssignment->firstOrFail()->assignment->project->name,
                    'hours' => $onAssignment->sum('hours'),
                ])
                ->sortBy('project')
                ->values(),
            'total' => $entries->sum('hours'),
            'assignments' => $employee->assignments()
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
                ->values(),
            'canManage' => $canManage,
            'isOwn' => $employee->is($actor),
        ]);
    }

    /**
     * Every day of the Period, each with its Work Entries and their total. Each
     * Work Entry carries its state, who approved and locked it, and whether
     * the viewer can change or delete it: both need a Pending Work Entry,
     * changing needs what recording does, and deleting needs only manage
     * work entries for the Employee, the top Employee included.
     *
     * A weekday covered by Pending or Approved Time Off says whether that
     * Time Off is Paid or Unpaid. A past weekday while the Employee works
     * here, with neither Work Entries nor such Time Off, is nothing logged.
     *
     * @param  Collection<int, WorkEntry>  $entries
     * @param  Collection<int, TimeOff>  $timeOff  the Employee's Pending and Approved Time Off overlapping the Period
     * @return array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int, time_off: string|null, nothing_logged: bool}>
     */
    private function days(Employee $employee, Period $period, Collection $entries, Collection $timeOff, bool $canManage, bool $canDelete): array
    {
        return collect($period->days())->map(function (CarbonImmutable $day) use ($employee, $entries, $timeOff, $canManage, $canDelete): array {
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
                    'approved' => $entry->approval(),
                    'locked' => $entry->lock(),
                    'can_edit' => $canManage && $entry->isPending(),
                    'can_delete' => $canDelete && $entry->isPending(),
                ])->values()->all(),
                'hours' => $onDay->sum('hours'),
                'time_off' => $away === null ? null : ($away->is_paid ? 'paid' : 'unpaid'),
                'nothing_logged' => $day->isWeekday() && $day->isBefore(today()) && $employed && $onDay->isEmpty() && $away === null,
            ];
        })->all();
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
     * Back to the Period the page sent in its query while the Work Entry
     * falls within it, or else to the week it falls in.
     */
    private function backTo(Request $request, Employee $employee, WorkEntry $workEntry): RedirectResponse
    {
        $period = Period::fromQuery($request->query());

        if ($period === null || ! $period->contains($workEntry->date)) {
            $period = Period::week($workEntry->date);
        }

        return to_route('work-entries.index', ['employee' => $employee->id, ...$period->query()]);
    }
}
