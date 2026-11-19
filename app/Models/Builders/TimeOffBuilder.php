<?php

namespace App\Models\Builders;

use App\Enums\TimeOffState;
use App\Models\TimeOff;
use Carbon\CarbonInterface;

/**
 * Every reusable query on Time Off.
 *
 * @extends ModelBuilder<TimeOff>
 */
class TimeOffBuilder extends ModelBuilder
{
    /**
     * Limit the query to Pending Time Off, which waits for the Employee's
     * Manager to approve or decline it.
     */
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TimeOffState::Pending);
        });
    }

    /**
     * Limit the query to Declined Time Off, which the Employee's Manager
     * turned down.
     */
    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TimeOffState::Declined);
        });
    }

    /**
     * Limit the query to Time Off that still stands: Pending or Approved.
     */
    public function standing(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn('state', TimeOffState::standing());
        });
    }

    /**
     * Limit the query to Time Off the Employee can still cancel: Pending or
     * Approved, and not started yet.
     */
    public function cancellable(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn($this->qualifyColumn('state'), TimeOffState::standing())
                ->whereDate($this->qualifyColumn('first_date'), '>', today());
        });
    }

    /**
     * Limit the query to Paid Time Off, which uses the Employee's Paid Days.
     */
    public function paid(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('is_paid'), true);
        });
    }

    /**
     * Limit the query to Time Off with at least one date from the first to
     * the last date given.
     */
    public function overlapping(CarbonInterface $first, CarbonInterface $last): static
    {
        return $this->scoped(function () use ($first, $last): void {
            $this->whereDate('first_date', '<=', $last)->whereDate('last_date', '>=', $first);
        });
    }

    /**
     * Limit the query to Time Off that has the day as one of its days: a
     * weekday from its first to its last date. Weekends are never days of
     * Time Off, so none has a Saturday or Sunday.
     */
    public function onDay(CarbonInterface $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->overlapping($day, $day)->when($day->isWeekend(), fn (self $none) => $none->whereRaw('false'));
        });
    }

    /**
     * Limit the query to the Time Off of the given Employees.
     */
    public function ofEmployees(EmployeeBuilder $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn($this->qualifyColumn('employee_id'), $employees->select('employees.id'));
        });
    }
}
