<?php

namespace App\Models\Builders;

use App\Enums\Permission;
use App\Models\Assignment;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Assignments.
 *
 * @extends Builder<Assignment>
 */
class AssignmentBuilder extends Builder
{
    /**
     * Limit the query to Internal Assignments.
     */
    public function internal(): static
    {
        $this->whereRelation('project', 'is_internal', true);

        return $this;
    }

    /**
     * Limit the query to Assignments to Projects other than the Internal
     * Project.
     */
    public function notInternal(): static
    {
        $this->whereRelation('project', 'is_internal', false);

        return $this;
    }

    /**
     * Limit the query to the Assignments the Employee may see, matching
     * AssignmentPolicy::viewAny().
     */
    public function visibleTo(Employee $actor): static
    {
        $this->whereHas('employee', fn (Builder $employees) => $actor->limitToReachOf($employees, Permission::seeingAssignments()));

        return $this;
    }

    /**
     * Limit the query to Assignments running on the day: started by then and
     * not yet ended. The end date counts as a day of the Assignment.
     */
    public function runningOn(CarbonInterface $day): static
    {
        $this->whereDate('start_date', '<=', $day)
            ->where(fn (Builder $running) => $running->whereNull('end_date')->orWhereDate('end_date', '>=', $day));

        return $this;
    }

    /**
     * Order Assignments by their Employee's name, then from the earliest.
     */
    public function orderByEmployeeName(): static
    {
        $this->orderBy(Employee::query()->select('name')->whereColumn('employees.id', 'assignments.employee_id'))
            ->orderBy('employee_id')
            ->orderBy('start_date')
            ->orderBy('id');

        return $this;
    }
}
