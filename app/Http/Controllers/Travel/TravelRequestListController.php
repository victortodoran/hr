<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelExpenseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestListRequest;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Builders\TravelRequestBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\PageWithin;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Travel Requests page: the Travel Requests of the Employees the viewer
 * may see travel of, one Period at a time, for every Employee or one, each
 * with a summary of its Travel Expenses. Travel Expenses are only ever
 * shown as part of their Travel Request, so they are reopened on its page.
 */
class TravelRequestListController extends Controller
{
    /**
     * The Travel Requests matching the filters, those with a day in the
     * Period, in every state unless one is chosen, by first date, with the
     * total of their Travel Expenses that aren't Declined in the company
     * currency. The Employees to choose from are those the viewer may see
     * with Travel Requests in the Period in the chosen state, by name with
     * how many; one not among them falls back to every Employee. The
     * Projects to choose from are those the chosen Employee, or anyone, has
     * Travel Requests on in the Period; one not among them falls back to
     * any Project.
     */
    public function index(#[CurrentUser] Employee $actor, TravelRequestListRequest $request): Response
    {
        $currency = CompanySettings::payCurrency();
        $requested = $request->filters();
        $visible = fn (): EmployeeBuilder => Employee::query()->withTravelVisibleTo($actor);
        $inPeriod = fn (TravelRequestBuilder $travelRequests): TravelRequestBuilder => $travelRequests->filteredBy(['period' => $requested['period'], 'state' => $requested['state']]);
        $employees = $visible()
            ->whereHas('travelRequests', $inPeriod)
            ->withCount(['travelRequests as travel_requests_count' => $inPeriod])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
            ->get(['employees.id', 'name'])
            ->map(fn (Employee $employee): array => [...$employee->only('id', 'name'), 'count' => (int) $employee->getAttribute('travel_requests_count')])
            ->all();
        $employee = collect($employees)->firstWhere('id', $requested['employee'])['id'] ?? 'all';
        $ofEmployee = fn (): EmployeeBuilder => $employee === 'all' ? $visible() : Employee::query()->whereKey($employee);
        $projects = Project::query()
            ->whereHas('travelRequests', fn (TravelRequestBuilder $travelRequests): TravelRequestBuilder => $inPeriod($travelRequests)->ofEmployees($ofEmployee()))
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
        $matching = TravelRequest::query()
            ->ofEmployees($visible())
            ->filteredBy([...$filters, 'employee' => is_int($employee) ? $employee : null]);
        $notDeclined = fn (Builder $expenses): Builder => $expenses->where('state', '!=', TravelExpenseState::Declined);

        return Inertia::render('travel-requests/List', [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'travelRequests' => PageWithin::paginate((clone $matching)
                ->with(['employee:id,name', 'assignment.project:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->withCount(['travelExpenses as expenses_count', 'travelExpenses as pending_expenses_count' => fn (Builder $expenses): Builder => $expenses->where('state', TravelExpenseState::Pending)])
                ->withSum(['travelExpenses as expenses_total' => $notDeclined], 'amount')
                ->orderBy('first_date')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(fn (TravelRequest $travelRequest): array => [
                    'id' => $travelRequest->id,
                    'employee' => $travelRequest->employee->only('id', 'name'),
                    'project' => $travelRequest->assignment->project->name,
                    'destination' => $travelRequest->destination,
                    'first_date' => $travelRequest->first_date->toDateString(),
                    'last_date' => $travelRequest->last_date->toDateString(),
                    'state' => $travelRequest->state->value,
                    'expenses' => (int) $travelRequest->getAttribute('expenses_count') === 0 ? null : [
                        'total' => $currency->format((int) $travelRequest->getAttribute('expenses_total')),
                        'pending' => (int) $travelRequest->getAttribute('pending_expenses_count'),
                    ],
                ]),
            'total' => $currency->format((int) $notDeclined(TravelExpense::query()->whereIn('travel_request_id', (clone $matching)->select('travel_requests.id')))->sum('amount')),
            'employees' => $employees,
            'projects' => $projects,
        ]);
    }
}
