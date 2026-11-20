<?php

namespace App\Models\Builders;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;

/** @extends ModelBuilder<TravelExpense> */
class TravelExpenseBuilder extends ModelBuilder
{
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelExpenseState::Pending);
        });
    }

    public function approved(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelExpenseState::Approved);
        });
    }

    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelExpenseState::Declined);
        });
    }

    public function decidableBy(Employee $actor): static
    {
        return $this->scoped(function () use ($actor): void {
            $this->pending()->ofEmployees(Employee::query()->effectivelyManagedBy($actor));
        });
    }

    public function notDeclined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), '!=', TravelExpenseState::Declined);
        });
    }

    /** @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters */
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

    /** @param  EmployeeBuilder|array<int, int|string>  $employees */
    public function ofEmployees(EmployeeBuilder|array $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn($this->qualifyColumn('travel_request_id'), TravelRequest::query()
                ->select('id')
                ->whereIn('employee_id', $employees instanceof EmployeeBuilder ? $employees->select('employees.id') : $employees));
        });
    }
}
