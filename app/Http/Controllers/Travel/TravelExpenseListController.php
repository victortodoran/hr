<?php

namespace App\Http\Controllers\Travel;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelExpenseListRequest;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TravelExpense;
use App\Support\PageWithin;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Travel Expenses page: Travel Expenses of the Employees the viewer may
 * see, one Period and one Employee at a time, or every Employee at once,
 * with reopening and locking many of them at once, as the Work Entries page
 * does for hours. Who is listed comes from the viewer's reopen Scope, or
 * everyone for someone who can lock. Reopening is done by the
 * TravelExpenseReopeningController and locking by the
 * TravelExpenseLockController; approving happens on the Approvals page.
 */
class TravelExpenseListController extends Controller
{
    /**
     * The Travel Expenses matching the filters, in every state unless one is
     * chosen, oldest first, with the total of those that aren't Declined in
     * the company currency. The Employees to choose from are those the
     * viewer may see with Travel Expenses paid in the Period in the chosen
     * state, by name with how many; an Employee not among them, or none,
     * falls back to the first, while "all" keeps every Employee. The
     * Projects to choose from are those the chosen Employee, or anyone, has
     * Travel Expenses on during the Period; one not among them falls back
     * to any Project.
     */
    public function index(#[CurrentUser] Employee $actor, TravelExpenseListRequest $request): Response
    {
        $currency = CompanySettings::payCurrency();
        $canLock = $actor->can('lock', TravelExpense::class);
        $requested = $request->filters();
        $visible = fn (): EmployeeBuilder => Employee::query()->withTravelExpensesVisibleTo($actor);
        $inPeriod = fn (TravelExpenseBuilder $expenses): TravelExpenseBuilder => $expenses->filteredBy(['period' => $requested['period'], 'state' => $requested['state']]);
        $employees = $visible()
            ->whereHas('travelExpenses', $inPeriod)
            ->withCount(['travelExpenses as expenses_count' => $inPeriod])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
            ->get(['employees.id', 'name'])
            ->map(fn (Employee $employee): array => [...$employee->only('id', 'name'), 'count' => (int) $employee->getAttribute('expenses_count')])
            ->all();
        $employee = $requested['employee'] === 'all'
            ? 'all'
            : collect($employees)->firstWhere('id', $requested['employee'])['id'] ?? $employees[0]['id'] ?? null;
        $projects = $employees === [] ? [] : Project::query()
            ->whereHas('travelRequests.travelExpenses', fn (TravelExpenseBuilder $expenses): TravelExpenseBuilder => $inPeriod($expenses)
                ->ofEmployees($employee === 'all' ? $visible() : Employee::query()->whereKey($employee)))
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Projects that share a name)
            ->get(['projects.id', 'name'])
            ->map(fn (Project $project): array => $project->only('id', 'name'))
            ->all();
        $filters = [
            'period' => $requested['period'],
            'employee' => $employee,
            'project' => collect($projects)->firstWhere('id', $requested['project'])['id'] ?? null,
            'state' => $requested['state'],
        ];
        $matching = TravelExpense::query()
            ->ofEmployees($visible())
            ->filteredBy([...$filters, 'employee' => is_int($employee) ? $employee : null]);
        $reopenable = Employee::query()->reopenableBy($actor, Permission::ReopenTravelExpenses)->pluck('id');

        return Inertia::render('travel-expenses/List', [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'canReopen' => $actor->holds(Permission::ReopenTravelExpenses),
            'canLock' => $canLock,
            'expenses' => PageWithin::paginate((clone $matching)
                ->with(['travelRequest.employee:id,name', 'travelRequest.assignment.project:id,name', 'approver:id,name', 'locker:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('paid_on')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(fn (TravelExpense $expense): array => [
                    'id' => $expense->id,
                    'paid_on' => $expense->paid_on->toDateString(),
                    'employee' => $expense->travelRequest->employee->only('id', 'name'),
                    'project' => $expense->travelRequest->assignment->project->name,
                    'destination' => $expense->travelRequest->destination,
                    'category' => $expense->category->value,
                    'description' => $expense->description,
                    'amount' => $currency->format($expense->amount),
                    'state' => $expense->state->value,
                    'approved' => $expense->approval(),
                    'locked' => $expense->lock(),
                    'decline_reason' => $expense->decline_reason,
                    'can_reopen' => $expense->state === TravelExpenseState::Approved && $reopenable->contains($expense->travelRequest->employee_id),
                    'can_lock' => $expense->state === TravelExpenseState::Approved && $canLock,
                ]),
            'total' => $currency->format((int) (clone $matching)->where('travel_expenses.state', '!=', TravelExpenseState::Declined)->sum('amount')),
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $employees,
            'projects' => $projects,
        ]);
    }
}
