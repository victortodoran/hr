<?php

namespace App\Models\Builders;

use App\Enums\WorkEntryState;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** @extends ModelBuilder<WorkEntry> */
class WorkEntryBuilder extends ModelBuilder
{
    public function pending(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), WorkEntryState::Pending);
        });
    }

    public function approved(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), WorkEntryState::Approved);
        });
    }

    public function declined(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), WorkEntryState::Declined);
        });
    }

    public function decidableBy(Employee $actor): static
    {
        return $this->scoped(function () use ($actor): void {
            $this->pending()->ofEmployees(Employee::query()->effectivelyManagedBy($actor));
        });
    }

    public function counted(): static
    {
        return $this->scoped(function (): void {
            $this->where($this->qualifyColumn('state'), '!=', WorkEntryState::Declined);
        });
    }

    public function onWeekdaysBetween(CarbonInterface $first, CarbonInterface $last): static
    {
        return $this->scoped(function () use ($first, $last): void {
            $this->whereDate($this->qualifyColumn('date'), '>=', $first)
                ->whereDate($this->qualifyColumn('date'), '<=', $last)
                ->whereRaw('extract(isodow from work_entries.date) < 6');
        });
    }

    public function toCopyOnto(CarbonImmutable $day): static
    {
        return $this->scoped(function () use ($day): void {
            $this->counted()
                ->whereDate($this->qualifyColumn('date'), '>=', WorkEntry::copyLookbackFrom($day))
                ->whereDate($this->qualifyColumn('date'), '<', $day);
        });
    }

    /** @param  array{employee?: int|null, project?: int|null, period?: Period|null, state?: string|null}  $filters */
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

    public function ofEmployees(EmployeeBuilder $employees): static
    {
        return $this->scoped(function () use ($employees): void {
            $this->whereIn('assignment_id', Assignment::query()
                ->select('id')
                ->whereIn('employee_id', $employees->select('employees.id')));
        });
    }

    /**
     * "all" stands for any Employee.
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
