<?php

namespace App\Http\Controllers\Employees;

use App\Actions\AddEmployee;
use App\Actions\UpdateTrimmingAssignments;
use App\Enums\EmployeePageTab;
use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Assignment;
use App\Models\Builders\EmployeeBuilder;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Support\EmployeeForm;
use App\Support\EmployeePage;
use Illuminate\Container\Attributes\CurrentUser;
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
            ->where(fn (EmployeeBuilder $query) => $scope->limit($query, $actor))
            ->when($status, fn (EmployeeBuilder $query, EmploymentStatus $status) => $query->inEmploymentStatus($status))
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
        return Inertia::render('employees/Create', EmployeeForm::choices());
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
     * Show an Employee's page in tabs, each offered only when the viewer may
     * see something on it. Overview: their record, the reports needing a new
     * Manager when they have left, and their Reporting Chain unless they have
     * left. Assignments & Pay: their Assignments, with Pay for viewers allowed
     * to see it. Time Off: their Paid Days to set, and their Time Off with
     * this year's Paid Days left. Travel: their Travel Requests with their
     * Travel Expenses, for viewers view travel reaches them with. Only the
     * open tab's data is loaded, apart from the Employee's employment
     * status, which the header shows on every tab to viewers who may see
     * their record; an unknown or forbidden tab opens Overview.
     */
    public function show(#[CurrentUser] Employee $actor, Employee $employee, Request $request): Response
    {
        $canViewAssignments = $actor->can('viewAny', [Assignment::class, $employee]);
        $canManage = $actor->can('create', [Assignment::class, $employee]);
        $canViewPay = $actor->can('viewPay', [Assignment::class, $employee]);
        $canManagePay = $actor->can('managePay', [Assignment::class, $employee]);
        $canSetPaidDays = $actor->can('setPaidDays', $employee);
        $canViewTimeOff = $actor->can('viewTimeOffAndPaidDays', $employee);
        $currency = $canViewPay ? CompanySettings::payCurrency() : null;
        $canViewRecord = $actor->can(Permission::ViewEmployeeRecords->value, $employee);
        $canViewTravel = $actor->can(Permission::ViewTravel->value, $employee);

        $tabs = array_values(array_filter([
            EmployeePageTab::Overview,
            $canViewAssignments ? EmployeePageTab::Assignments : null,
            $canSetPaidDays || $canViewTimeOff ? EmployeePageTab::TimeOff : null,
            $canViewTravel ? EmployeePageTab::Travel : null,
        ]));
        $query = $request->query('tab');
        $asked = is_string($query) ? EmployeePageTab::tryFrom($query) : null;
        $tab = in_array($asked, $tabs, true) ? $asked : EmployeePageTab::Overview;

        return Inertia::render('employees/Show', [
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
                EmployeePageTab::Overview => EmployeePage::overviewTab($actor, $employee, $canViewRecord),
                EmployeePageTab::Assignments => EmployeePage::assignmentsTab($employee, $currency, $canManage),
                EmployeePageTab::TimeOff => EmployeePage::timeOffTab($employee, $canSetPaidDays, $canViewTimeOff),
                EmployeePageTab::Travel => EmployeePage::travelTab($employee),
            },
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
                'can_resend_invitation' => $actor->can('resendInvitation', $employee),
            ],
            'reportsNeedingAManager' => EmployeePage::reportsNeedingAManager($employee),
            ...EmployeeForm::choices($employee),
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
}
