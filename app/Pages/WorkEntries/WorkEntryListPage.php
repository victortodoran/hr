<?php

namespace App\Pages\WorkEntries;

use App\Enums\Permission;
use App\Enums\SpreadsheetFormat;
use App\Enums\WorkEntryState;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Builders\WorkEntryBuilder;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkEntry;
use App\Support\PageWithin;
use App\Support\Period;
use App\Support\WorkEntrySpreadsheet;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkEntryListPage
{
    /**
     * The Employee asked for is offered even with no Work Entries, as an Employee's page links here for them.
     *
     * @param  array{period: Period, employee: int|'all'|null, project: int|null, state: string|null}  $requested
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, array $requested): array
    {
        $visible = fn (): EmployeeBuilder => Employee::query()->withWorkEntriesVisibleTo($actor);
        $inPeriod = fn (WorkEntryBuilder $entries): WorkEntryBuilder => self::inPeriod($entries, $requested['period'], $requested['state']);
        $employees = $visible()
            ->where(fn (EmployeeBuilder $listed) => $listed
                ->whereHas('workEntries', $inPeriod)
                ->when(is_int($requested['employee']), fn (EmployeeBuilder $asked) => $asked->orWhereKey($requested['employee'])))
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
            ->whereHas('workEntries', fn (WorkEntryBuilder $entries): WorkEntryBuilder => $inPeriod($entries)
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
        $matching = WorkEntry::query()->listedFor($actor, $filters);
        $reopenable = Employee::query()->reopenableBy($actor, Permission::ReopenWorkEntries)->pluck('id');

        return [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'canReopen' => $actor->holds(Permission::ReopenWorkEntries),
            'entries' => PageWithin::paginate((clone $matching)
                ->with(['assignment.employee:id,name', 'assignment.project:id,name', 'approver:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('date')
                ->orderBy('id'), 25)
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
                    'declined' => $entry->decline(),
                    'can_reopen' => $entry->state === WorkEntryState::Approved && $reopenable->contains($entry->assignment->employee_id),
                ]),
            'total' => (int) (clone $matching)->counted()->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'declinedTotal' => (int) (clone $matching)->declined()->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $employees,
            'projects' => $projects,
        ];
    }

    /**
     * With `ownOnly`, the file holds the viewer's own Work Entries, as their Work page shows them.
     *
     * @param  array{period: Period, employee: int|'all'|null, project: int|null, state: string|null}  $filters
     */
    public static function download(Employee $actor, array $filters, SpreadsheetFormat $format, bool $ownOnly = false): StreamedResponse
    {
        $entries = ($ownOnly
            ? WorkEntry::query()->ofEmployees(Employee::query()->whereKey($actor->id))->filteredBy([...$filters, 'employee' => null])
            : WorkEntry::query()->listedFor($actor, $filters))
            ->with(['assignment.employee:id,name', 'assignment.project:id,name,client_id', 'assignment.project.client:id,name', 'approver:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same rows)
            ->orderBy('date')
            ->orderBy('id');

        return WorkEntrySpreadsheet::download($entries, $format, $filters['period']);
    }

    private static function inPeriod(WorkEntryBuilder $entries, Period $period, ?string $state): WorkEntryBuilder
    {
        return $entries->filteredBy(['period' => $period, 'state' => $state]);
    }
}
