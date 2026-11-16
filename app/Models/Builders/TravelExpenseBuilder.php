<?php

namespace App\Models\Builders;

use App\Enums\TravelExpenseState;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

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
