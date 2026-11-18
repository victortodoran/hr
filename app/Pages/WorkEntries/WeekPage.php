<?php

namespace App\Pages\WorkEntries;

use App\Enums\WorkEntriesTab;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\Period;
use App\Support\WorkEntryDays;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The Work Entries week page as the props it renders: one Employee's Work
 * Entries for one Period, a week from Monday to Sunday, a month or a custom
 * period, day by day, with totals for each day, each Assignment and the
 * Period.
 */
final class WeekPage
{
    /**
     * The Employee's Work Entries in the Period, as the open tab lays them
     * out, with the totals that count and those Declined, and what the
     * viewer may act on.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, Employee $employee, Period $period, WorkEntriesTab $tab, bool $canManage, bool $canDelete): array
    {
        $entries = $employee->workEntries()
            ->filteredBy(['period' => $period])
            ->with(['assignment.project:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('work_entries.date')
            ->orderBy('work_entries.id')
            ->get();
        $declined = $entries->filter(fn (WorkEntry $entry): bool => $entry->isDeclined());
        $counted = $entries->diff($declined);
        $timeOff = $employee->timeOff()->standing()->overlapping($period->from, $period->to)->get();
        $days = WorkEntryDays::of($employee, $period, $entries, $timeOff, $canManage, $canDelete);

        return [
            'tab' => $tab->value,
            'employee' => $employee->only('id', 'name'),
            'period' => $period->toArray(),
            'today' => today()->toDateString(),
            'total' => $counted->sum('hours'),
            'declinedHours' => $declined->sum('hours'),
            'assignmentTotals' => self::assignmentTotals($counted),
            'assignments' => $canManage ? self::assignments($employee, $period) : [],
            'canManage' => $canManage,
            'isOwn' => $employee->is($actor),
            ...match ($tab) {
                WorkEntriesTab::List => ['days' => $days->days(), 'weeks' => $days->weeks()],
                WorkEntriesTab::Calendar => ['calendar' => $days->calendar()],
            },
        ];
    }

    /**
     * The Employee's Assignments that run during the Period, by Project
     * name, for adding and changing Work Entries; only sent to a viewer who
     * can record the Employee's hours.
     *
     * @return SupportCollection<int, array{id: int, project: string, job_title: string|null, start_date: string, end_date: string|null}>
     */
    private static function assignments(Employee $employee, Period $period): SupportCollection
    {
        return $employee->assignments()
            ->runningDuring($period)
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
    private static function assignmentTotals(Collection $entries): SupportCollection
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
}
