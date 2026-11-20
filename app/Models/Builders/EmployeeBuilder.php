<?php

namespace App\Models\Builders;

use App\Enums\EmploymentStatus;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Permission;
use App\Models\Concerns\SearchesByName;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** @extends ModelBuilder<Employee> */
class EmployeeBuilder extends ModelBuilder
{
    use SearchesByName;

    /** Anyone with a Leaving Date soon can't sign in, so doesn't count. */
    public function lastingRoleManagers(): static
    {
        return $this->scoped(function (): void {
            $this->whereDate('start_date', '<=', today())
                ->whereNull('leaving_date')
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->where('is_admin', true)
                    ->orWhereHas('permissions', fn (Builder $grants) => $grants->where('permission', Permission::ManageRoles)));
        });
    }

    /** Walks down past only those who have left, so it reads just the Manager's part of the line; it must match withEffectiveManager() for today. */
    public function effectivelyManagedBy(Employee $manager): static
    {
        return $this->scoped(function () use ($manager): void {
            $today = today()->toDateString();

            $this->whereRaw(<<<'SQL'
                employees.id IN (
                    WITH RECURSIVE down AS (
                        SELECT reports.id, reports.leaving_date FROM employees AS reports
                        WHERE reports.manager_id = ? AND EXISTS (
                            SELECT 1 FROM employees AS managers
                            WHERE managers.id = ? AND (managers.leaving_date IS NULL OR managers.leaving_date >= ?)
                        )
                        UNION
                        SELECT reports.id, reports.leaving_date FROM employees AS reports
                        JOIN down ON reports.manager_id = down.id
                        WHERE down.leaving_date < ?
                    )
                    SELECT id FROM down
                )
                SQL, [$manager->id, $manager->id, $today, $today]);
        });
    }

    public function reportsOf(Employee $manager): static
    {
        return $this->scoped(function () use ($manager): void {
            $this->whereRaw(<<<'SQL'
                employees.id IN (
                    WITH RECURSIVE below AS (
                        SELECT id FROM employees WHERE manager_id = ?
                        UNION
                        SELECT employees.id FROM employees JOIN below ON employees.manager_id = below.id
                    )
                    SELECT id FROM below
                )
                SQL, [$manager->id]);
        });
    }

    public function aboveInReportingLine(Employee $employee): static
    {
        return $this->scoped(function () use ($employee): void {
            $this->whereRaw(<<<'SQL'
                employees.id IN (
                    WITH RECURSIVE above AS (
                        SELECT manager_id AS id FROM employees WHERE id = ?
                        UNION
                        SELECT employees.manager_id FROM employees JOIN above ON employees.id = above.id
                    )
                    SELECT id FROM above
                )
                SQL, [$employee->id]);
        });
    }

    /** Being someone's Manager doesn't count, so without the Permission it's nobody. */
    public function reopenableBy(Employee $reopener, Permission $reopen): static
    {
        return $this->scoped(function () use ($reopener, $reopen): void {
            $reopener->limitToReachOf($this, [$reopen]);
            $this->whereKeyNot($reopener->id);
        });
    }

    public function withRecordVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $viewer->limitToReachOf($this, [Permission::ViewEmployeeRecords]);
        });
    }

    /** Must match EmployeePolicy::view(). */
    public function withPageVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $this->where(fn (EmployeeBuilder $visible) => $visible
                ->whereKey($viewer->id)
                ->orWhere(fn (EmployeeBuilder $reached) => $viewer->limitToReachOf($reached, Permission::seeingEmployeePages())));
        });
    }

    public function findableBy(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $this->notFormer()
                ->where(fn (EmployeeBuilder $findable) => $findable
                    ->withPageVisibleTo($viewer)
                    ->when($viewer->holds(Permission::ViewDirectory), fn (EmployeeBuilder $orListed) => $orListed
                        ->orWhere(fn (EmployeeBuilder $listed) => $listed->current())));
        });
    }

    /** Must match WorkEntryPolicy::viewAny(). */
    public function withWorkEntriesVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $viewer->limitToReachOf($this, Permission::seeingWorkEntries());
        });
    }

    public function withTravelVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $viewer->limitToReachOf($this, Permission::seeingTravel());
        });
    }

    /** Must match EmployeePolicy::viewTimeOffAndPaidDays(). */
    public function withTimeOffVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $viewer->limitToReachOf($this, Permission::seeingTimeOff());
        });
    }

    /** Leaves out the viewer, whose own are on the Performance page. */
    public function withPerformanceEvaluationsVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $this->whereKeyNot($viewer->id)
                ->where(fn (EmployeeBuilder $visible) => $visible
                    ->whereIn('employees.id', Employee::query()->effectivelyManagedBy($viewer)->select('employees.id'))
                    ->orWhere(fn (EmployeeBuilder $reached) => $viewer->limitToReachOf($reached, Permission::seeingPerformanceEvaluations())));
        });
    }

    public function withWorkForTheirManager(): static
    {
        return $this->scoped(function (): void {
            $this->where(fn (Builder $pending) => $pending
                ->whereHas('workEntries', fn (WorkEntryBuilder $entries) => $entries->pending())
                ->orWhereHas('timeOff', fn (TimeOffBuilder $timeOff) => $timeOff->pending())
                ->orWhereHas('travelRequests', fn (TravelRequestBuilder $travelRequests) => $travelRequests->pending())
                ->orWhereHas('travelRequests.travelExpenses', fn (TravelExpenseBuilder $expenses) => $expenses->pending())
                ->orWhereHas('performanceEvaluations', fn (PerformanceEvaluationBuilder $evaluations) => $evaluations->open()));
        });
    }

    public function awaitingPerformanceEvaluationFor(int $year): static
    {
        return $this->scoped(function () use ($year): void {
            $this->employedDuring($year)
                ->whereDoesntHave('performanceEvaluations', fn (PerformanceEvaluationBuilder $evaluations) => $evaluations->where('year', $year));
        });
    }

    public function withPerformanceEvaluationFor(int $year, ?PerformanceEvaluationState $state = null): static
    {
        return $this->scoped(function () use ($year, $state): void {
            $this->whereHas('performanceEvaluations', fn (PerformanceEvaluationBuilder $evaluations) => $evaluations
                ->where('year', $year)
                ->when($state, fn (PerformanceEvaluationBuilder $inState, PerformanceEvaluationState $state) => $inState->where('state', $state)));
        });
    }

    public function awaitingPerformanceEvaluationFrom(Employee $manager, int $year): static
    {
        return $this->scoped(function () use ($manager, $year): void {
            if (! in_array($year, PerformanceEvaluation::creatableYears(), true)) {
                $this->whereRaw('false');

                return;
            }

            $this->effectivelyManagedBy($manager)->awaitingPerformanceEvaluationFor($year);
        });
    }

    public function employedDuring(int $year): static
    {
        return $this->scoped(function () use ($year): void {
            $this->whereDate('start_date', '<=', "{$year}-12-31")
                ->where(fn (Builder $staying) => $staying->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', "{$year}-01-01"));
        });
    }

    public function current(): static
    {
        return $this->scoped(function (): void {
            $this->whereDate('start_date', '<=', today())->notFormer();
        });
    }

    public function notFormer(): static
    {
        return $this->scoped(function (): void {
            $this->where(fn (Builder $staying) => $staying->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', today()));
        });
    }

    public function inEmploymentStatus(EmploymentStatus $status): static
    {
        return $this->scoped(function () use ($status): void {
            match ($status) {
                EmploymentStatus::Future => $this->whereDate('start_date', '>', today()),
                EmploymentStatus::Current => $this->current(),
                EmploymentStatus::Former => $this->whereDate('leaving_date', '<', today()),
            };
        });
    }

    /** Worked out when read, so nothing needs reassigning when a Manager leaves; the walk only climbs past Managers who have left, so it ends at the one row picked. */
    public function withEffectiveManager(?CarbonInterface $on = null): static
    {
        return $this->scoped(function () use ($on): void {
            $day = ($on ?? today())->toDateString();

            if ($this->getQuery()->columns === null) {
                $this->select('employees.*');
            }

            $this->selectRaw(<<<'SQL'
                (
                    WITH RECURSIVE up AS (
                        SELECT managers.id, managers.manager_id, managers.leaving_date
                        FROM employees AS managers WHERE managers.id = employees.manager_id
                        UNION ALL
                        SELECT managers.id, managers.manager_id, managers.leaving_date
                        FROM employees AS managers JOIN up ON managers.id = up.manager_id
                        WHERE up.leaving_date < ?
                    )
                    SELECT id FROM up WHERE up.leaving_date IS NULL OR up.leaving_date >= ? LIMIT 1
                ) AS effective_manager_id
                SQL, [$day, $day]);
        });
    }
}
