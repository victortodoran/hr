<?php

namespace App\Pages\Travel;

use App\Enums\SpreadsheetFormat;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\Builders\TravelRequestBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\PageWithin;
use App\Support\Period;
use App\Support\TravelExpenseSpreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Travel Requests page as the props it renders: the Travel Requests of
 * the Employees the viewer may see travel of, one Period at a time, for
 * every Employee or one, each with a summary of its Travel Expenses.
 */
final class TravelRequestListPage
{
    /**
     * The Travel Requests matching the filters, those with a day in the
     * Period, in every state unless one is chosen, by first date, with the
     * total of their Travel Expenses that aren't Declined in the company
     * currency. The Employees to choose from are those the viewer may see
     * with Travel Requests in the Period in the chosen state, by name with
     * how many, and the one asked for when the viewer may see them, even
     * with none, as an Employee's page links here for them; one not among
     * them falls back to every Employee. The
     * Projects to choose from are those the chosen Employee, or anyone, has
     * Travel Requests on in the Period; one not among them falls back to
     * any Project.
     *
     * @param  array{period: Period, employee: int|'all', project: int|null, state: string|null}  $requested
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, array $requested): array
    {
        $currency = CompanySettings::payCurrency();
        $visible = fn (): EmployeeBuilder => Employee::query()->withTravelVisibleTo($actor);
        $inPeriod = fn (TravelRequestBuilder $travelRequests): TravelRequestBuilder => $travelRequests->filteredBy(['period' => $requested['period'], 'state' => $requested['state']]);
        $employees = $visible()
            ->where(fn (EmployeeBuilder $listed) => $listed
                ->whereHas('travelRequests', $inPeriod)
                ->when(is_int($requested['employee']), fn (EmployeeBuilder $asked) => $asked->orWhereKey($requested['employee'])))
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

        return [
            'filters' => $filters,
            'today' => today()->toDateString(),
            'travelRequests' => PageWithin::paginate((clone $matching)
                ->with(['employee:id,name', 'assignment.project:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->withCount(['travelExpenses as expenses_count', 'travelExpenses as pending_expenses_count' => fn (TravelExpenseBuilder $expenses): TravelExpenseBuilder => $expenses->pending()])
                ->withSum(['travelExpenses as expenses_total' => fn (TravelExpenseBuilder $expenses): TravelExpenseBuilder => $expenses->notDeclined()], 'amount')
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
            'total' => $currency->format((int) TravelExpense::query()->whereIn('travel_request_id', (clone $matching)->select('travel_requests.id'))->notDeclined()->sum('amount')),
            'employees' => $employees,
            'projects' => $projects,
        ];
    }

    /**
     * The Approved Travel Expenses paid in the Period, of the Employees the
     * viewer may see travel of, narrowed to the Employee and Project when
     * one is given, as a CSV or Excel file named after the Period, by date
     * paid. An Employee the viewer may not see leaves the file empty.
     *
     * @param  array{period: Period, employee: int|'all', project: int|null}  $filters
     */
    public static function download(Employee $actor, array $filters, SpreadsheetFormat $format): StreamedResponse
    {
        $expenses = TravelExpense::query()
            ->approved()
            ->ofEmployees(Employee::query()->withTravelVisibleTo($actor))
            ->filteredBy([...$filters, 'employee' => is_int($filters['employee']) ? $filters['employee'] : null])
            ->with('travelRequest.employee:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same rows)
            ->orderBy('paid_on')
            ->orderBy('id');

        return TravelExpenseSpreadsheet::download($expenses, $format, $filters['period']);
    }
}
