<?php

namespace App\Pages\Employees;

use App\Enums\EmployeePageTab;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\Project;
use App\Models\TimeOff;
use App\Support\Currency;
use App\Support\PaidDaysLeft;
use App\Support\PayHistory;
use App\Support\ReportingChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Employee page as the props it renders, in tabs: Overview,
 * Assignments & Pay and Time Off. Each tab is only built when it is open.
 * The Overview's Reports needing a new Manager are shown on the edit form
 * too.
 */
final class EmployeePage
{
    /**
     * An Employee's page in tabs, each offered only when the viewer may see
     * something on it. Overview: their record, the reports needing a new
     * Manager when they have left, and their Reporting Chain unless they
     * have left. Assignments & Pay: their Assignments, with Pay for viewers
     * allowed to see it. Time Off: their Paid Days to set, and their Time
     * Off with this year's Paid Days left. Only the open tab's data is
     * loaded, apart from the Employee's employment status, which the header
     * shows on every tab to viewers who may see their record; an unknown or
     * forbidden tab opens Overview.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, Employee $employee, ?EmployeePageTab $asked): array
    {
        $canViewAssignments = $actor->can('viewAny', [Assignment::class, $employee]);
        $canManage = $actor->can('create', [Assignment::class, $employee]);
        $canViewPay = $actor->can('viewPay', [Assignment::class, $employee]);
        $canManagePay = $actor->can('managePay', [Assignment::class, $employee]);
        $canSetPaidDays = $actor->can('setPaidDays', $employee);
        $canViewTimeOff = $actor->can('viewTimeOffAndPaidDays', $employee);
        $currency = $canViewPay ? CompanySettings::payCurrency() : null;
        $canViewRecord = $actor->can(Permission::ViewEmployeeRecords->value, $employee);

        $tabs = array_values(array_filter([
            EmployeePageTab::Overview,
            $canViewAssignments ? EmployeePageTab::Assignments : null,
            $canSetPaidDays || $canViewTimeOff ? EmployeePageTab::TimeOff : null,
        ]));
        $tab = in_array($asked, $tabs, true) ? $asked : EmployeePageTab::Overview;

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->internalAssignment?->job_title,
            ],
            'tab' => $tab->value,
            'tabs' => array_map(fn (EmployeePageTab $offered): string => $offered->value, $tabs),
            'canManage' => $canManage,
            'canViewPay' => $canViewPay,
            'canManagePay' => $canManagePay,
            ...($canManagePay && $currency !== null ? ['currency' => $currency->code] : []),
            'canEdit' => $actor->can('update', $employee),
            'standing' => $canViewRecord
                ? ['status' => $employee->employmentStatus()->value]
                : null,
            ...match ($tab) {
                EmployeePageTab::Overview => self::overviewTab($actor, $employee, $canViewRecord),
                EmployeePageTab::Assignments => self::assignmentsTab($employee, $currency, $canManage),
                EmployeePageTab::TimeOff => self::timeOffTab($employee, $canSetPaidDays, $canViewTimeOff),
            },
        ];
    }

    /**
     * The Overview tab: the Employee's record and the reports needing a new
     * Manager, when the viewer may see the record, and their Reporting Chain
     * unless they have left.
     *
     * @return array{record: array<string, mixed>|null, reportsNeedingAManager: array<int, array{id: int, name: string}>, reportingChain: array<string, mixed>|null}
     */
    private static function overviewTab(Employee $actor, Employee $employee, bool $canViewRecord): array
    {
        return [
            'record' => $canViewRecord ? self::record($employee) : null,
            'reportsNeedingAManager' => $canViewRecord ? self::reportsNeedingAManager($employee) : [],
            'reportingChain' => $employee->employmentStatus() === EmploymentStatus::Former ? null : ReportingChain::of($employee, $actor),
        ];
    }

    /**
     * The Assignments & Pay tab: the Employee's Assignments, with Pay when
     * a currency is given, and the Projects they can be put on, to those
     * who may manage their Assignments.
     *
     * @return array{assignments: array<int, array{id: int, project: array{id: int, name: string, client: string|null, is_internal: bool}, job_title: string|null, start_date: string, end_date: string|null, capacity: int, has_ended: bool, can_delete: bool, pay?: array<int, array{id: int, effective_from: string, kind: string, amount: string, is_in_effect: bool}>}>, projects: array<int, array{id: int, name: string, client: string|null, start_date: string|null, end_date: string|null}>}
     */
    private static function assignmentsTab(Employee $employee, ?Currency $currency, bool $canManage): array
    {
        return [
            'assignments' => self::assignments($employee, $currency, $canManage),
            'projects' => $canManage ? self::openProjects() : [],
        ];
    }

    /**
     * The Time Off tab: the Employee's Paid Days, to those who may set them,
     * and their Time Off, to those who may see it.
     *
     * @return array{paidDays: int|null, timeOff: array<string, mixed>|null}
     */
    private static function timeOffTab(Employee $employee, bool $canSetPaidDays, bool $canViewTimeOff): array
    {
        return [
            'paidDays' => $canSetPaidDays ? $employee->paid_days : null,
            'timeOff' => $canViewTimeOff ? self::timeOff($employee) : null,
        ];
    }

    /**
     * When the Employee has left, the Employees whose Manager is still set to
     * them, one level down, and who need a new Manager.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function reportsNeedingAManager(Employee $employee): array
    {
        if ($employee->employmentStatus() !== EmploymentStatus::Former) {
            return [];
        }

        return Employee::query()
            ->where('manager_id', $employee->id)
            ->orderBy('name')
            ->get(['id', 'name', 'start_date', 'leaving_date'])
            ->reject(fn (Employee $report): bool => $report->employmentStatus() === EmploymentStatus::Former)
            ->map(fn (Employee $report): array => ['id' => $report->id, 'name' => $report->name])
            ->values()
            ->all();
    }

    /**
     * The Employee's record as their page shows it, with the Manager they
     * effectively report to.
     *
     * @return array{email: string, start_date: string, leaving_date: string|null, department: string|null, manager: array{id: int, name: string}|null}
     */
    private static function record(Employee $employee): array
    {
        $effective = Employee::query()->withEffectiveManager()->findOrFail($employee->id);

        return [
            'email' => $effective->email,
            'start_date' => $effective->start_date->toDateString(),
            'leaving_date' => $effective->leaving_date?->toDateString(),
            'department' => $effective->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
            'manager' => $effective->effectiveManager?->only('id', 'name'),
        ];
    }

    /**
     * The Employee's Paid Days, their Paid Days left this year and, from
     * October, next year, and their Time Off in every state, latest first.
     *
     * @return array{paid_days: int, paid_days_left: list<array{year: int, days: int}>, requests: array<int, array{id: int, first_date: string, last_date: string, days: int, is_paid: bool, state: string, decided_by: string|null, decline_reason: string|null}>}
     */
    private static function timeOff(Employee $employee): array
    {
        return [
            'paid_days' => $employee->paid_days,
            'paid_days_left' => PaidDaysLeft::of($employee),
            'requests' => $employee->timeOff()
                ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderByDesc('first_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (TimeOff $timeOff): array => $timeOff->details())
                ->all(),
        ];
    }

    /**
     * The Employee's Assignments, earliest first, each with its Pay history
     * when a currency is given, which only happens for viewers allowed to see
     * the Employee's Pay, and whether each can be deleted, for viewers who
     * may manage the Employee's Assignments.
     *
     * @return array<int, array{id: int, project: array{id: int, name: string, client: string|null, is_internal: bool}, job_title: string|null, start_date: string, end_date: string|null, capacity: int, has_ended: bool, can_delete: bool, pay?: array<int, array{id: int, effective_from: string, kind: string, amount: string, is_in_effect: bool}>}>
     */
    private static function assignments(Employee $employee, ?Currency $currency, bool $canManage): array
    {
        return $employee->assignments()
            ->with('project.client')
            ->when($canManage, fn (Builder $query) => $query->withExists(['workEntries', 'travelRequests']))
            ->when($currency !== null, fn (Builder $query) => $query->with(['payEntries' => fn (HasMany $entries) => $entries->orderByDesc('effective_from')]))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'project' => [
                    'id' => $assignment->project->id,
                    'name' => $assignment->project->name,
                    'client' => $assignment->project->client?->name,
                    'is_internal' => $assignment->project->is_internal,
                ],
                'job_title' => $assignment->job_title,
                'start_date' => $assignment->start_date->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
                'capacity' => $assignment->capacity,
                'has_ended' => $assignment->end_date !== null && $assignment->end_date->isBefore(today()),
                'can_delete' => $canManage && $assignment->canBeDeleted(),
                ...($currency === null ? [] : ['pay' => self::payHistory($assignment, $currency)]),
            ])
            ->all();
    }

    /**
     * The Assignment's Pay history, newest first, marking the entry in
     * effect today.
     *
     * @return array<int, array{id: int, effective_from: string, kind: string, amount: string, is_in_effect: bool}>
     */
    private static function payHistory(Assignment $assignment, Currency $currency): array
    {
        $inEffect = PayHistory::inEffectOn(
            $assignment->payEntries->keyBy(fn (PayEntry $entry): string => $entry->effective_from->toDateString())->all(),
            today(),
        );

        return $assignment->payEntries
            ->map(fn (PayEntry $entry): array => [
                'id' => $entry->id,
                'effective_from' => $entry->effective_from->toDateString(),
                'kind' => $entry->kind->value,
                'amount' => $currency->format($entry->amount),
                'is_in_effect' => $entry->is($inEffect),
            ])
            ->all();
    }

    /**
     * The Projects an Employee can be put on: every Project except the
     * Internal Project and those that have finished, by name.
     *
     * @return array<int, array{id: int, name: string, client: string|null, start_date: string|null, end_date: string|null}>
     */
    private static function openProjects(): array
    {
        return Project::query()
            ->with('client:id,name')
            ->notInternal()
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->name,
                ...$project->dates(),
            ])
            ->all();
    }
}
