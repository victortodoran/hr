<?php

namespace App\Pages\WorkEntries;

use App\Enums\ApprovalsTab;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\Builders\WorkEntryBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Support\PageWithin;
use App\Support\PaidDaysLeft;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * The Approvals page, an inbox of what waits for the viewer's decision as an
 * effective Manager, as the props it renders: how many Work Entries, Time
 * Off, Travel Requests and Travel Expenses wait, and the open tab's list.
 * Only the open tab's list is loaded.
 */
final class ApprovalsPage
{
    /**
     * The open tab, with how many Work Entries, Time Off, Travel Requests and
     * Travel Expenses wait for the viewer's decision.
     *
     * @param  array{employee: int|null, project: int|null}  $requested
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, ApprovalsTab $tab, array $requested): array
    {
        return [
            'tab' => $tab->value,
            'waiting' => $actor->waitingForDecision(),
            ...match ($tab) {
                ApprovalsTab::WorkEntries => self::workEntriesTab($actor, $requested),
                ApprovalsTab::TimeOff => ['toApprove' => self::timeOffToApprove($actor)],
                ApprovalsTab::TravelRequests => ['travelRequestsToApprove' => self::travelRequestsToApprove($actor)],
                ApprovalsTab::TravelExpenses => ['travelExpensesToApprove' => self::travelExpensesToApprove($actor)],
            },
        ];
    }

    /**
     * The Work Entries tab, one Employee and one Project at a time: the
     * Pending Work Entries of the chosen Employee on the chosen Project,
     * oldest first, with their total hours. The Employees to choose from are
     * those whose effective Manager the viewer is and who have Pending Work
     * Entries, and the Projects those the chosen Employee has Pending Work
     * Entries on, each by name with how many. An Employee or Project not
     * among them, or none, falls back to the first, so approving the last of
     * one moves on to the first left. With nothing Pending at all, there is
     * nobody to choose.
     *
     * @param  array{employee: int|null, project: int|null}  $requested
     * @return array<string, mixed>
     */
    private static function workEntriesTab(Employee $actor, array $requested): array
    {
        $isPending = fn (WorkEntryBuilder $entries): WorkEntryBuilder => $entries->pending();
        $employees = Employee::query()
            ->effectivelyManagedBy($actor)
            ->whereHas('workEntries', $isPending)
            ->withCount(['workEntries as pending' => $isPending])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
            ->get(['id', 'name'])
            ->map(fn (Employee $employee): array => [...$employee->only('id', 'name'), 'pending' => (int) $employee->getAttribute('pending')])
            ->all();
        $employee = collect($employees)->firstWhere('id', $requested['employee']) ?? $employees[0] ?? null;
        $isPendingOfEmployee = fn (WorkEntryBuilder $entries): WorkEntryBuilder => $entries->pending()->ofEmployees(Employee::query()->whereKey($employee['id'] ?? null));
        $projects = $employee === null ? [] : Project::query()
            ->whereHas('workEntries', $isPendingOfEmployee)
            ->withCount(['workEntries as pending' => $isPendingOfEmployee])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Projects that share a name)
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => [...$project->only('id', 'name'), 'pending' => (int) $project->getAttribute('pending')])
            ->all();
        $project = collect($projects)->firstWhere('id', $requested['project']) ?? $projects[0] ?? null;
        $filters = [
            'employee' => $employee['id'] ?? null,
            'project' => $project['id'] ?? null,
        ];
        $matching = WorkEntry::query()
            ->pending()
            ->ofEmployees(Employee::query()->whereKey($filters['employee']))
            ->whereRelation('assignment', 'project_id', $filters['project']);

        return [
            'filters' => $filters,
            'entries' => PageWithin::paginate((clone $matching)
                ->with(['assignment.employee:id,name', 'assignment.project:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
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
                    'submitted_on' => $entry->created_at?->toDateString(),
                ]),
            'total' => (int) (clone $matching)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'weeks' => self::weeksOf($matching),
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $employees,
            'projects' => $projects,
        ];
    }

    /**
     * Every week the matching Work Entries fall in, earliest first, by its
     * Monday, with the hours of all of them in it, not only those on the
     * page shown, so a week split across pages keeps its full subtotal.
     *
     * @return list<array{starts_on: string, ends_on: string, number: int, hours: int|float}>
     */
    private static function weeksOf(WorkEntryBuilder $matching): array
    {
        return array_values((clone $matching)
            ->get(['date', 'minutes'])
            ->groupBy(fn (WorkEntry $entry): string => $entry->date->startOfWeek()->toDateString())
            ->sortKeys()
            ->map(fn (Collection $entries, string $monday): array => [
                'starts_on' => $monday,
                'ends_on' => CarbonImmutable::parse($monday)->addDays(6)->toDateString(),
                'number' => CarbonImmutable::parse($monday)->isoWeek,
                'hours' => (int) $entries->sum('minutes') / 60,
            ])
            ->all());
    }

    /**
     * The Time Off tab: the Pending Time Off of the Employees whose effective
     * Manager the actor is, earliest first, each with that Employee's Paid
     * Days left in every year it touches. Paid Days left already count the
     * Pending Time Off.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function timeOffToApprove(Employee $actor): array
    {
        return TimeOff::query()
            ->ofEmployees(Employee::query()->effectivelyManagedBy($actor))
            ->pending()
            ->with('employee:id,name,paid_days') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TimeOff $timeOff): array => [
                'id' => $timeOff->id,
                'employee' => $timeOff->employee->only('id', 'name'),
                'first_date' => $timeOff->first_date->toDateString(),
                'last_date' => $timeOff->last_date->toDateString(),
                'days' => $timeOff->weekdays(),
                'is_paid' => $timeOff->is_paid,
                'submitted_on' => $timeOff->created_at?->toDateString(),
                'paid_days_left' => PaidDaysLeft::in($timeOff->employee, range($timeOff->first_date->year, $timeOff->last_date->year)),
            ])
            ->all();
    }

    /**
     * The Travel Requests tab: the Pending Travel Requests of the Employees
     * whose effective Manager the actor is, earliest first, each with its
     * Assignment's Project and Client and the days it counts.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function travelRequestsToApprove(Employee $actor): array
    {
        return TravelRequest::query()
            ->ofEmployees(Employee::query()->effectivelyManagedBy($actor))
            ->pending()
            ->with(['employee:id,name', 'assignment.project.client:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TravelRequest $travelRequest): array => [
                'id' => $travelRequest->id,
                'employee' => $travelRequest->employee->only('id', 'name'),
                'project' => $travelRequest->assignment->project->name,
                'client' => $travelRequest->assignment->project->client?->name,
                'destination' => $travelRequest->destination,
                'first_date' => $travelRequest->first_date->toDateString(),
                'last_date' => $travelRequest->last_date->toDateString(),
                'days' => $travelRequest->days(),
                'purpose' => $travelRequest->purpose,
                'submitted_on' => $travelRequest->created_at?->toDateString(),
            ])
            ->all();
    }

    /**
     * The Travel Expenses tab: the Pending Travel Expenses of the Employees
     * whose effective Manager the actor is, grouped by their Travel Request,
     * earliest travel first, and within it by the date paid. Each amount is
     * shown in the company currency.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function travelExpensesToApprove(Employee $actor): array
    {
        $currency = CompanySettings::payCurrency();

        return TravelRequest::query()
            ->ofEmployees(Employee::query()->effectivelyManagedBy($actor))
            ->whereHas('travelExpenses', fn (TravelExpenseBuilder $expenses) => $expenses->pending())
            ->with(['employee:id,name', 'travelExpenses' => fn (HasMany $expenses) => $expenses->pending()->orderBy('paid_on')->orderBy('id')])
            ->orderBy('first_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TravelRequest $travelRequest): array => [
                'id' => $travelRequest->id,
                'employee' => $travelRequest->employee->only('id', 'name'),
                'destination' => $travelRequest->destination,
                'first_date' => $travelRequest->first_date->toDateString(),
                'last_date' => $travelRequest->last_date->toDateString(),
                'expenses' => $travelRequest->travelExpenses
                    ->map(fn (TravelExpense $travelExpense): array => [
                        'id' => $travelExpense->id,
                        'paid_on' => $travelExpense->paid_on->toDateString(),
                        'category' => $travelExpense->category->value,
                        'description' => $travelExpense->description,
                        'amount' => $currency->format($travelExpense->amount),
                        'submitted_on' => $travelExpense->created_at?->toDateString(),
                        'receipt_is_image' => in_array(strtolower(pathinfo($travelExpense->receipt_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                    ])
                    ->all(),
            ])
            ->all();
    }
}
