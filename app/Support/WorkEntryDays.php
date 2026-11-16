<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * An Employee's Work Entries in a Period, day by day, as the Work Entries
 * page shows them: the List tab's days and ISO weeks, and the Calendar
 * tab's weeks of seven days.
 */
final class WorkEntryDays
{
    /**
     * @param  array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int, time_off: string|null, nothing_logged: bool, copy_from: string|null}>  $days
     */
    private function __construct(private readonly array $days) {}

    /**
     * Lay out the Employee's Work Entries and Time Off in the Period, as the
     * viewer may act on them.
     *
     * @param  Collection<int, WorkEntry>  $entries  the Employee's Work Entries in the Period, Declined ones included
     * @param  Collection<int, TimeOff>  $timeOff  the Employee's Pending and Approved Time Off overlapping the Period
     */
    public static function of(Employee $employee, Period $period, Collection $entries, Collection $timeOff, bool $canManage, bool $canDelete): self
    {
        $copySources = $canManage
            ? self::datesWithEntries($employee, $period, $entries->reject(fn (WorkEntry $entry): bool => $entry->isDeclined()))
            : collect();

        return new self(self::layOut($employee, $period, $entries, $timeOff, $canManage, $canDelete, $copySources));
    }

    /**
     * The List tab's days.
     *
     * @return array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int, time_off: string|null, nothing_logged: bool, copy_from: string|null}>
     */
    public function days(): array
    {
        return $this->days;
    }

    /**
     * The Period's days split into ISO weeks, Monday to Sunday, each with
     * its number and the first and last of its days that fall within the
     * Period, and their hours.
     *
     * @return list<array{number: int, from: string, to: string, hours: float|int}>
     */
    public function weeks(): array
    {
        $weeks = [];

        foreach ($this->days as $day) {
            $date = CarbonImmutable::parse($day['date']);
            $monday = $date->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $weeks[$monday] ??= ['number' => $date->isoWeek, 'from' => $day['date'], 'to' => $day['date'], 'hours' => 0];
            $weeks[$monday]['to'] = $day['date'];
            $weeks[$monday]['hours'] += $day['hours'];
        }

        return array_values($weeks);
    }

    /**
     * The Calendar tab: the month's ISO weeks, each with its number, its
     * hours, and its seven days from Monday to Sunday, null for a day of
     * another month. Each day is the List's day, so it can be added to,
     * copied onto and have Work Entries deleted from the Calendar.
     *
     * @return list<array{number: int, hours: float|int, days: list<array<string, mixed>|null>}>
     */
    public function calendar(): array
    {
        $byDate = collect($this->days)->keyBy('date');

        return array_map(function (array $week) use ($byDate): array {
            $monday = CarbonImmutable::parse($week['from'])->startOfWeek(CarbonInterface::MONDAY);

            return [
                'number' => $week['number'],
                'hours' => $week['hours'],
                'days' => array_map(fn (int $offset): ?array => $byDate->get($monday->addDays($offset)->toDateString()), range(0, 6)),
            ];
        }, $this->weeks());
    }

    /**
     * Every day of the Period, each with its Work Entries and the hours of
     * those that count, all but the Declined. Each Work Entry carries its
     * state, who declined it and why, and whether the viewer can change or
     * delete it: both need a Pending or Declined Work Entry, changing needs
     * what recording does, and deleting needs only manage work entries for
     * the Employee, one with no Manager included.
     *
     * A weekday covered by Pending or Approved Time Off says whether that
     * Time Off is Paid or Unpaid. A past weekday while the Employee works
     * here, with neither Work Entries nor such Time Off, is nothing logged.
     * A day up to today with no Work Entries but Declined ones and no such
     * Time Off, when the viewer can record hours, names the day copying Work
     * Entries onto it would copy from: the latest day in the week before
     * with any that aren't Declined, or null.
     *
     * @param  Collection<int, WorkEntry>  $entries
     * @param  Collection<int, TimeOff>  $timeOff  the Employee's Pending and Approved Time Off overlapping the Period
     * @param  SupportCollection<int, string>  $copySources  the dates, in order, the Employee has Work Entries on from a week before the Period
     * @return array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int, time_off: string|null, nothing_logged: bool, copy_from: string|null}>
     */
    private static function layOut(Employee $employee, Period $period, Collection $entries, Collection $timeOff, bool $canManage, bool $canDelete, SupportCollection $copySources): array
    {
        return collect($period->days())->map(function (CarbonImmutable $day) use ($employee, $entries, $timeOff, $canManage, $canDelete, $copySources): array {
            $date = $day->toDateString();
            $onDay = $entries->filter(fn (WorkEntry $entry): bool => $entry->date->toDateString() === $date);
            $countedOnDay = $onDay->reject(fn (WorkEntry $entry): bool => $entry->isDeclined());
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
                    'can_edit' => $canManage && $entry->isOpen(),
                    'can_delete' => $canDelete && $entry->isOpen(),
                    'declined' => $entry->decline(),
                ])->values()->all(),
                'hours' => $countedOnDay->sum('hours'),
                'time_off' => $away === null ? null : ($away->is_paid ? 'paid' : 'unpaid'),
                'nothing_logged' => $day->isWeekday() && $day->isBefore(today()) && $employed && $onDay->isEmpty() && $away === null,
                'copy_from' => $canManage && ! $day->isAfter(today()) && $away === null && $countedOnDay->isEmpty()
                    ? $copySources->last(fn (string $source): bool => $source < $date && $source >= $day->subDays(WorkEntry::COPY_LOOKBACK_DAYS)->toDateString())
                    : null,
            ];
        })->all();
    }

    /**
     * The dates, in order, the Employee has Work Entries that aren't
     * Declined on from a week before the Period to its end: those in the
     * week before are looked up, those within it come from its Work Entries.
     *
     * @param  Collection<int, WorkEntry>  $entries  the Employee's Work Entries in the Period that aren't Declined
     * @return SupportCollection<int, string>
     */
    private static function datesWithEntries(Employee $employee, Period $period, Collection $entries): SupportCollection
    {
        $before = $employee->workEntries()
            ->counted()
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
}
