<?php

namespace App\Models\Builders;

use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\Employee;
use App\Support\Period;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Assignments.
 *
 * @extends ModelBuilder<Assignment>
 */
class AssignmentBuilder extends ModelBuilder
{
    /**
     * Limit the query to Internal Assignments.
     */
    public function internal(): static
    {
        return $this->scoped(function (): void {
            $this->whereRelation('project', 'is_internal', true);
        });
    }

    /**
     * Limit the query to Assignments to Projects other than the Internal
     * Project.
     */
    public function notInternal(): static
    {
        return $this->scoped(function (): void {
            $this->whereRelation('project', 'is_internal', false);
        });
    }

    /**
     * Limit the query to the Assignments the Employee may see, matching
     * AssignmentPolicy::viewAny().
     */
    public function visibleTo(Employee $actor): static
    {
        return $this->scoped(function () use ($actor): void {
            $this->whereHas('employee', fn (EmployeeBuilder $employees) => $actor->limitToReachOf($employees, Permission::seeingAssignments()));
        });
    }

    /**
     * Limit the query to Assignments running on the day: started by then and
     * not yet ended. The end date counts as a day of the Assignment.
     */
    public function runningOn(CarbonInterface $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->whereDate('start_date', '<=', $day)
                ->where(fn (Builder $running) => $running->whereNull('end_date')->orWhereDate('end_date', '>=', $day));
        });
    }

    /**
     * Limit the query to Assignments running on at least one day of the
     * Period: started by its last day and not ended before its first.
     */
    public function runningDuring(Period $period): static
    {
        return $this->scoped(function () use ($period): void {
            $this->whereDate('start_date', '<=', $period->to)
                ->where(fn (Builder $running) => $running->whereNull('end_date')->orWhereDate('end_date', '>=', $period->from));
        });
    }

    /**
     * Limit the query to Assignments not ended before the day: with no end
     * date, or ending on it or later.
     */
    public function notEndedBefore(CarbonInterface $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->where(fn (Builder $open) => $open->whereNull('end_date')->orWhereDate('end_date', '>=', $day));
        });
    }

    /**
     * Order Assignments by their Employee's name, then from the earliest.
     */
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
