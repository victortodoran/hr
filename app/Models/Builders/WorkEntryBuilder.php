<?php

namespace App\Models\Builders;

use App\Enums\WorkEntryState;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Work Entries.
 *
 * @extends Builder<WorkEntry>
 */
class WorkEntryBuilder extends Builder
{
    /**
     * Limit the query to the Work Entries whose hours count: all but the
     * Declined ones.
     */
    public function counted(): static
    {
        $this->where($this->qualifyColumn('state'), '!=', WorkEntryState::Declined);

        return $this;
    }

    /**
     * Limit the query to the Work Entries matching the filters, each applied
     * only when set: an Employee, a Project, a Period and a state.
     *
     * @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters
     */
    public function filteredBy(array $filters): static
    {
        $this->when($filters['employee'] ?? null, fn (Builder $entries, int $employee) => $entries->whereRelation('assignment', 'employee_id', $employee))
            ->when($filters['project'] ?? null, fn (Builder $entries, int $project) => $entries->whereRelation('assignment', 'project_id', $project))
            ->when($filters['period'] ?? null, fn (Builder $entries, Period $period) => $entries->whereBetween($entries->qualifyColumn('date'), [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ]))
            ->when($filters['state'] ?? null, fn (Builder $entries, string $state) => $entries->where($entries->qualifyColumn('state'), $state));

        return $this;
    }

    /**
     * Limit the query to the Work Entries of the given Employees, on any of
     * their Assignments.
     */
    public function ofEmployees(EmployeeBuilder $employees): static
    {
        $this->whereIn('assignment_id', Assignment::query()
            ->select('id')
            ->whereIn('employee_id', $employees->select('employees.id')));

        return $this;
    }
}
