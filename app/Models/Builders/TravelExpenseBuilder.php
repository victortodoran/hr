<?php

namespace App\Models\Builders;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Travel Expenses.
 *
 * @extends ModelBuilder<TravelExpense>
 */
class TravelExpenseBuilder extends ModelBuilder
{
    /**
     * Limit the query to Pending Travel Expenses, which wait for the
     * Employee's Manager to approve or decline them.
     */
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelExpenseState::Pending);
        });
    }

    /**
     * Limit the query to Approved Travel Expenses, which can be reopened.
     */
    public function approved(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelExpenseState::Approved);
        });
    }

    /**
     * Limit the query to Declined Travel Expenses, whose amount counts
     * nowhere.
     */
    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelExpenseState::Declined);
        });
    }

    /**
     * Limit the query to the Travel Expenses the actor may approve or
     * decline: Pending ones of the Employees whose effective Manager they
     * are, so never their own.
     */
    public function decidableBy(Employee $actor): static
    {
        return $this->scoped(function () use ($actor): void {
            $this->pending()->ofEmployees(Employee::query()->effectivelyManagedBy($actor));
        });
    }

    /**
     * Limit the query to the Travel Expenses whose amount counts towards
     * a total: all but the Declined ones.
     */
    public function notDeclined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), '!=', TravelExpenseState::Declined);
        });
    }

    /**
     * Limit the query to the filters given, each optional: an Employee and
     * a Project, by ID, the Period they were paid in and a state.
     *
     * @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters
     */
    public function filteredBy(array $filters): static
    {
        return $this->scoped(function () use ($filters): void {
            $this->when($filters['employee'] ?? null, fn (Builder $expenses, int $employee) => $expenses->whereRelation('travelRequest', 'employee_id', $employee))
                ->when($filters['project'] ?? null, fn (Builder $expenses, int $project) => $expenses->whereRelation('travelRequest.assignment', 'project_id', $project))
                ->when($filters['period'] ?? null, fn (Builder $expenses, Period $period) => $expenses->whereBetween($expenses->qualifyColumn('paid_on'), [
                    $period->from->toDateString(),
                    $period->to->toDateString(),
                ]))
                ->when($filters['state'] ?? null, fn (Builder $expenses, string $state) => $expenses->where($expenses->qualifyColumn('state'), $state));
        });
    }

    /**
     * Limit the query to the Travel Expenses of the given Employees, as a
     * query or by ID.
     *
     * @param  EmployeeBuilder|array<int, int|string>  $employees
     */
    public function ofEmployees(EmployeeBuilder|array $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn($this->qualifyColumn('travel_request_id'), TravelRequest::query()
                ->select('id')
                ->whereIn('employee_id', $employees instanceof EmployeeBuilder ? $employees->select('employees.id') : $employees));
        });
    }
}
