<?php

namespace App\Models\Builders;

use App\Enums\TimeOffState;
use App\Models\TimeOff;
use App\Support\Period;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** @extends ModelBuilder<TimeOff> */
class TimeOffBuilder extends ModelBuilder
{
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TimeOffState::Pending);
        });
    }

    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), TimeOffState::Declined);
        });
    }

    public function standing(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn('state', TimeOffState::standing());
        });
    }

    public function cancellable(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn($this->qualifyColumn('state'), TimeOffState::standing())
                ->whereDate($this->qualifyColumn('first_date'), '>', today());
        });
    }

    public function paid(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('is_paid'), true);
        });
    }

    public function overlapping(CarbonInterface $first, CarbonInterface $last): static
    {
        return $this->scoped(function () use ($first, $last): void {
            $this->whereDate('first_date', '<=', $last)->whereDate('last_date', '>=', $first);
        });
    }

    public function onDay(CarbonInterface $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->overlapping($day, $day)->when($day->isWeekend(), fn (self $none) => $none->whereRaw('false'));
        });
    }

    /** @param  array{employee?: int|null, period?: Period|null, state?: string|null}  $filters */
    public function filteredBy(array $filters): static
    {
        return $this->scoped(function () use ($filters): void {
            $this->when($filters['employee'] ?? null, fn (Builder $timeOff, int $employee) => $timeOff->where($timeOff->qualifyColumn('employee_id'), $employee))
                ->when($filters['period'] ?? null, fn (self $timeOff, Period $period) => $timeOff->overlapping($period->from, $period->to))
                ->when($filters['state'] ?? null, fn (Builder $timeOff, string $state) => $timeOff->where($timeOff->qualifyColumn('state'), $state));
        });
    }

    public function ofEmployees(EmployeeBuilder $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn($this->qualifyColumn('employee_id'), $employees->select('employees.id'));
        });
    }
}
