<?php

namespace App\Models\Builders;

use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\Employee;
use App\Support\Period;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** @extends ModelBuilder<Assignment> */
class AssignmentBuilder extends ModelBuilder
{
    public function internal(): static
    {
        return $this->scoped(function (): void {
            $this->whereRelation('project', 'is_internal', true);
        });
    }

    public function notInternal(): static
    {
        return $this->scoped(function (): void {
            $this->whereRelation('project', 'is_internal', false);
        });
    }

    /** Must match AssignmentPolicy::viewAny(). */
    public function visibleTo(Employee $actor): static
    {
        return $this->scoped(function () use ($actor): void {
            $this->whereHas('employee', fn (EmployeeBuilder $employees) => $actor->limitToReachOf($employees, Permission::seeingAssignments()));
        });
    }

    /** The end date counts as a day of the Assignment. */
    public function runningOn(CarbonInterface $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->whereDate('start_date', '<=', $day)->notEndedBefore($day);
        });
    }

    public function runningDuring(Period $period): static
    {
        return $this->scoped(function () use ($period): void {
            $this->whereDate('start_date', '<=', $period->to)->notEndedBefore($period->from);
        });
    }

    public function notEndedBefore(CarbonInterface $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->where(fn (Builder $open) => $open->whereNull('end_date')->orWhereDate('end_date', '>=', $day));
        });
    }

    public function orderByEmployeeName(): static
    {
        return $this->scoped(function (): void {
            $this->orderBy(Employee::query()->select('name')->whereColumn('employees.id', 'assignments.employee_id'))
                ->orderBy('employee_id')
                ->orderBy('start_date')
                ->orderBy('id');
        });
    }
}
