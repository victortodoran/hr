<?php

namespace App\Models\Builders;

use App\Enums\WorkEntryState;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Work Entries.
 *
 * @extends ModelBuilder<WorkEntry>
 */
class WorkEntryBuilder extends ModelBuilder
{
    /**
     * Limit the query to Pending Work Entries, which wait for the Employee's
     * effective Manager to approve or decline them.
     */
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), WorkEntryState::Pending);
        });
    }

    /**
     * Limit the query to Approved Work Entries, which can be reopened.
     */
    public function approved(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), WorkEntryState::Approved);
        });
    }

    /**
     * Limit the query to Declined Work Entries, whose hours count nowhere.
     */
    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), WorkEntryState::Declined);
        });
    }

    /**
     * Limit the query to the Work Entries whose hours count: all but the
     * Declined ones.
     */
    public function counted(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), '!=', WorkEntryState::Declined);
        });
    }

    /**
     * Limit the query to the Work Entries that copying onto the day could
     * take from: those whose hours count, in the look-back window before it.
     */
    public function toCopyOnto(CarbonImmutable $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->counted()
                ->whereDate($this->qualifyColumn('date'), '>=', WorkEntry::copyLookbackFrom($day))
                ->whereDate($this->qualifyColumn('date'), '<', $day);
        });
    }

    /**
     * Limit the query to the Work Entries matching the filters, each applied
     * only when set: an Employee, a Project, a Period and a state.
     *
     * @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters
     */
    public function filteredBy(array $filters): static
    {
        return $this->scoped(function () use ($filters): void {
            $this->when($filters['employee'] ?? null, fn (Builder $entries, int $employee) => $entries->whereRelation('assignment', 'employee_id', $employee))
                ->when($filters['project'] ?? null, fn (Builder $entries, int $project) => $entries->whereRelation('assignment', 'project_id', $project))
                ->when($filters['period'] ?? null, fn (Builder $entries, Period $period) => $entries->whereBetween($entries->qualifyColumn('date'), [
                    $period->from->toDateString(),
                    $period->to->toDateString(),
                ]))
                ->when($filters['state'] ?? null, fn (Builder $entries, string $state) => $entries->where($entries->qualifyColumn('state'), $state));
        });
    }

    /**
     * Limit the query to the Work Entries of the given Employees, on any of
     * their Assignments.
     */
    public function ofEmployees(EmployeeBuilder $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn('assignment_id', Assignment::query()
                ->select('id')
                ->whereIn('employee_id', $employees->select('employees.id')));
        });
    }

    /**
     * Limit the query to the Work Entries the Work Entries page lists for the
     * viewer: those of the Employees whose Work Entries they may see, matching
     * the page's filters, where "all" stands for any Employee.
     *
     * @param  array{period: Period, employee: int|'all'|null, project: int|null, state: string|null}  $filters
     */
    public function listedFor(Employee $viewer, array $filters): static
    {
        return $this->scoped(function () use ($viewer, $filters): void {
            $this->ofEmployees(Employee::query()->withWorkEntriesVisibleTo($viewer))
                ->filteredBy([...$filters, 'employee' => is_int($filters['employee']) ? $filters['employee'] : null]);
        });
    }
}
