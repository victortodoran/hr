<?php

namespace App\Models\Builders;

use App\Enums\TravelRequestState;
use App\Models\TravelRequest;
use App\Support\Period;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Travel Requests.
 *
 * @extends ModelBuilder<TravelRequest>
 */
class TravelRequestBuilder extends ModelBuilder
{
    /**
     * Limit the query to Pending Travel Requests, which wait for the
     * Employee's Manager to approve or decline them.
     */
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelRequestState::Pending);
        });
    }

    /**
     * Limit the query to Declined Travel Requests, which the Employee's
     * Manager refused with a reason.
     */
    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TravelRequestState::Declined);
        });
    }

    /**
     * Limit the query to Travel Requests that still stand: Pending or
     * Approved.
     */
    public function standing(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn('state', TravelRequestState::standing());
        });
    }

    /**
     * Limit the query to Travel Requests their Employee may still cancel:
     * standing, and starting after today. The query form of
     * TravelRequest::isCancellable(), so the two change together.
     */
    public function cancellable(): static
    {
        return $this->scoped(function (): void {
            $this->standing()->whereDate($this->qualifyColumn('first_date'), '>', today());
        });
    }

    /**
     * Limit the query to Travel Requests with at least one date from the
     * first to the last date given.
     */
    public function overlapping(CarbonInterface $first, CarbonInterface $last): static
    {
        return $this->scoped(function () use ($first, $last): void {
            $this->whereDate('first_date', '<=', $last)->whereDate('last_date', '>=', $first);
        });
    }

    /**
     * Limit the query to the filters given, each optional: an Employee and
     * a Project, by ID, a Period they have at least one day in, and a
     * state.
     *
     * @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters
     */
    public function filteredBy(array $filters): static
    {
        return $this->scoped(function () use ($filters): void {
            $this->when($filters['employee'] ?? null, fn (Builder $travelRequests, int $employee) => $travelRequests->where($travelRequests->qualifyColumn('employee_id'), $employee))
                ->when($filters['project'] ?? null, fn (Builder $travelRequests, int $project) => $travelRequests->whereRelation('assignment', 'project_id', $project))
                ->when($filters['period'] ?? null, fn (Builder $travelRequests, Period $period) => $travelRequests
                    ->whereDate($travelRequests->qualifyColumn('first_date'), '<=', $period->to)
                    ->whereDate($travelRequests->qualifyColumn('last_date'), '>=', $period->from))
                ->when($filters['state'] ?? null, fn (Builder $travelRequests, string $state) => $travelRequests->where($travelRequests->qualifyColumn('state'), $state));
        });
    }

    /**
     * Limit the query to the Travel Requests of the given Employees.
     */
    public function ofEmployees(EmployeeBuilder $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn($this->qualifyColumn('employee_id'), $employees->select('employees.id'));
        });
    }
}
