<?php

namespace App\Http\Controllers;

use App\Actions\AddEmployee;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Requests\EmployeeRequest;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayEntry;
use App\Models\Project;
use App\Support\Currency;
use App\Support\PayHistory;
use App\Support\ReportingChain;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists, shows, adds and edits Employees, each as far as the viewer's
 * Permissions and Scope allow.
 */
class EmployeeController extends Controller
{
    /**
     * List the Employees whose record the viewer may see, by name, with each
     * one's status, optionally narrowed to one status or part of a name.
     */
    public function index(#[CurrentUser] Employee $actor, Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(EmploymentStatus::class)],
        ]);
        $search = $filters['search'] ?? '';
        $status = isset($filters['status']) ? EmploymentStatus::from($filters['status']) : null;
        /** @var Scope $scope the policy only lets in viewers who hold view employee records */
        $scope = $actor->widestScopeFor(Permission::ViewEmployeeRecords);

        $employees = Employee::query()
            ->where(fn (Builder $query) => $scope->limit($query, $actor))
            ->when($status, fn (Builder $query, EmploymentStatus $status) => $query->inEmploymentStatus($status))
            ->whereLike('name', '%'.addcslashes($search, '\\%_').'%')
            ->withEffectiveManager()
            ->with(['department:id,name', 'effectiveManager:id,name'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'status' => $employee->employmentStatus()->value,
                'department' => $employee->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                'manager' => $employee->effectiveManager?->only('id', 'name'),
            ]);

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'search' => $search,
            'status' => $status?->value,
            'canAdd' => $actor->can('create', Employee::class),
        ]);
    }

    /**
     * Show the form for adding an Employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/Create', $this->choices());
    }

    /**
     * Add an Employee and invite them to set their password.
     */
    public function store(#[CurrentUser] Employee $actor, EmployeeRequest $request, AddEmployee $addEmployee): RedirectResponse
    {
        /** @var array{name: string, email: string, department_id: int, manager_id: int, start_date: string} $details */
        $details = $request->validated();

        $employee = $addEmployee->add($actor, $details);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added. An invitation has been sent to :email.', ['email' => $employee->email])]);

        return to_route('employees.edit', $employee);
    }

    /**
     * Show an Employee's page: their record, then their Assignments and Pay,
     * and their Paid Days for this year and next, each only when the viewer
     * may see it, and their Reporting Chain unless they have left.
     */
    public function show(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        $canViewRecord = $actor->can(Permission::ViewEmployeeRecords->value, $employee);
        $canViewAssignments = $actor->can('viewAny', [Assignment::class, $employee]);
        $canManage = $actor->can('create', [Assignment::class, $employee]);
        $canViewPay = $actor->can('viewPay', [Assignment::class, $employee]);
        $canManagePay = $actor->can('managePay', [Assignment::class, $employee]);
        $currency = $canViewPay ? CompanySettings::payCurrency() : null;
        $thisYear = today()->year;

        return Inertia::render('employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->internalAssignment?->job_title,
            ],
            'record' => $canViewRecord ? $this->record($employee) : null,
            'reportsNeedingAManager' => $canViewRecord ? $this->reportsNeedingAManager($employee) : [],
            'reportingChain' => $employee->employmentStatus() === EmploymentStatus::Former ? null : ReportingChain::of($employee, $actor),
            'assignments' => $canViewAssignments ? $this->assignments($employee, $currency) : null,
            'canManage' => $canManage,
            'projects' => $canManage ? $this->openProjects() : [],
            'canViewPay' => $canViewPay,
            'canManagePay' => $canManagePay,
            ...($canManagePay && $currency !== null ? ['currency' => $currency->code] : []),
            'canEdit' => $actor->can('update', $employee),
            'paidDays' => $actor->can('setPaidDays', $employee)
                ? array_map(fn (int $year): array => ['year' => $year, 'days' => $employee->paidDaysIn($year)], [$thisYear, $thisYear + 1])
                : null,
        ]);
    }

    /**
     * Show the form for editing an Employee's record.
     */
    public function edit(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        return Inertia::render('employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'department_id' => $employee->department_id,
                'manager_id' => $employee->manager_id,
                'start_date' => $employee->start_date->toDateString(),
                'leaving_date' => $employee->leaving_date?->toDateString(),
                'status' => $employee->employmentStatus()->value,
                'is_top' => $employee->is_top,
                'can_resend_invitation' => $actor->can('resendInvitation', $employee),
                'can_become_top' => $actor->can('becomeTop', $employee),
            ],
            'reportsNeedingAManager' => $this->reportsNeedingAManager($employee),
            ...$this->choices($employee),
        ]);
    }

    /**
     * Save changes to an Employee's record. Their Assignments are trimmed
     * to end by their Leaving Date in the same transaction.
     */
    public function update(EmployeeRequest $request, Employee $employee, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateEmployee($employee, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.edit', $employee);
    }

    /**
     * The Employee's record as their page shows it, with the Manager they
     * effectively report to.
     *
     * @return array{email: string, status: string, start_date: string, leaving_date: string|null, department: string|null, manager: array{id: int, name: string}|null, is_top: bool}
     */
    private function record(Employee $employee): array
    {
        $effective = Employee::query()->withEffectiveManager()->findOrFail($employee->id);

        return [
            'email' => $effective->email,
            'status' => $effective->employmentStatus()->value,
            'start_date' => $effective->start_date->toDateString(),
            'leaving_date' => $effective->leaving_date?->toDateString(),
            'department' => $effective->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
            'manager' => $effective->effectiveManager?->only('id', 'name'),
            'is_top' => $effective->is_top,
        ];
    }

    /**
     * The Employee's Assignments, earliest first, each with its Pay history
     * when a currency is given, which only happens for viewers allowed to see
     * the Employee's Pay.
     *
     * @return array<int, array{id: int, project: array{id: int, name: string, client: string|null, is_internal: bool}, job_title: string|null, start_date: string, end_date: string|null, capacity: int, pay?: array<int, array{id: int, effective_from: string, kind: string, amount: string, is_in_effect: bool}>}>
     */
    private function assignments(Employee $employee, ?Currency $currency): array
    {
        return $employee->assignments()
            ->with('project.client')
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
                ...($currency === null ? [] : ['pay' => $this->payHistory($assignment, $currency)]),
            ])
            ->all();
    }

    /**
     * The Assignment's Pay history, newest first, marking the entry in
     * effect today.
     *
     * @return array<int, array{id: int, effective_from: string, kind: string, amount: string, is_in_effect: bool}>
     */
    private function payHistory(Assignment $assignment, Currency $currency): array
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
    private function openProjects(): array
    {
        return Project::query()
            ->with('client:id,name')
            ->where('is_internal', false)
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

    /**
     * When the Employee has left, the Employees whose Manager is still set to
     * them, one level down, and who need a new Manager.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function reportsNeedingAManager(Employee $employee): array
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
     * The Departments and possible Managers the form offers. Former
     * Employees can't become anyone's Manager, though an Employee's current
     * Manager stays on offer after leaving so the record can still be saved.
     *
     * @return array{departments: Collection<int, Department>, managers: array<int, array{id: int, name: string, status: string}>}
     */
    private function choices(?Employee $employee = null): array
    {
        $managers = Employee::query()
            ->orderBy('name')
            ->get(['id', 'name', 'start_date', 'leaving_date'])
            ->reject(fn (Employee $manager): bool => $manager->is($employee))
            ->filter(fn (Employee $manager): bool => $manager->employmentStatus() !== EmploymentStatus::Former
                || $manager->id === $employee?->manager_id);

        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'managers' => $managers
                ->map(fn (Employee $manager): array => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'status' => $manager->employmentStatus()->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
