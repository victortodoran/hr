<?php

namespace App\Pages\TimeOff;

use App\Models\Builders\EmployeeBuilder;
use App\Models\Builders\TimeOffBuilder;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Support\PageWithin;
use App\Support\Period;

/**
 * The Time Off page across Employees as the props it renders: the Time Off
 * of the Employees the viewer may see Time Off for, one Period at a time,
 * for every Employee or one.
 */
final class TimeOffListPage
{
    /**
     * The Time Off matching the filters, that with a date in the Period, in
     * every state unless one is chosen, by first date, 25 to a page. The
     * Employees to choose from are those the viewer may see with Time Off
     * in the Period in the chosen state, by name with how many; one not
     * among them falls back to every Employee.
     *
     * @param  array{period: Period, employee: int|'all', state: string|null}  $requested
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, array $requested): array
    {
        $visible = fn (): EmployeeBuilder => Employee::query()->withTimeOffVisibleTo($actor);
        $inPeriod = fn (TimeOffBuilder $timeOff): TimeOffBuilder => $timeOff->filteredBy(['period' => $requested['period'], 'state' => $requested['state']]);
        $employees = $visible()
            ->whereHas('timeOff', $inPeriod)
            ->withCount(['timeOff as time_off_count' => $inPeriod])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
            ->get(['employees.id', 'name'])
            ->map(fn (Employee $employee): array => [...$employee->only('id', 'name'), 'count' => (int) $employee->getAttribute('time_off_count')])
            ->all();
        $employee = collect($employees)->firstWhere('id', $requested['employee'])['id'] ?? 'all';
        $filters = [
            'period' => $requested['period'],
            'employee' => $employee,
            'state' => $requested['state'],
        ];

        return [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'timeOff' => PageWithin::paginate(TimeOff::query()
                ->ofEmployees($visible())
                ->filteredBy([...$filters, 'employee' => is_int($employee) ? $employee : null])
                ->with('employee:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('first_date')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(fn (TimeOff $timeOff): array => [
                    'id' => $timeOff->id,
                    'employee' => $timeOff->employee->only('id', 'name'),
                    'first_date' => $timeOff->first_date->toDateString(),
                    'last_date' => $timeOff->last_date->toDateString(),
                    'days' => $timeOff->weekdays(),
                    'is_paid' => $timeOff->is_paid,
                    'state' => $timeOff->state->value,
                ]),
            'employees' => $employees,
        ];
    }
}
