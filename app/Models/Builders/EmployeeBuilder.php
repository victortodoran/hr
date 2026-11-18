<?php

namespace App\Models\Builders;

use App\Enums\EmploymentStatus;
use App\Enums\PerformanceEvaluationState;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TimeOffState;
use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Enums\WorkEntryState;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Employees.
 *
 * @extends ModelBuilder<Employee>
 */
class EmployeeBuilder extends ModelBuilder
{
    /**
     * Limit the query to Employees who can manage Roles from now on: current
     * Employees with no Leaving Date who hold Admin or a Role granting manage
     * roles. Anyone else can't sign in, or soon won't be able to.
     */
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

    /**
     * Limit the query to the Employees whose effective Manager the given
     * Employee is: one level below them, Future and Former Employees
     * included. It walks down from the Manager, passing below only those
     * who have left, so it reads just their part of the reporting line; a
     * Manager who has left is nobody's effective Manager. It matches
     * withEffectiveManager() for today, which walks the same line up.
     */
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

    /**
     * Limit the query to the Manager's Reports: everyone below them in the
     * reporting line, the same Employees hasInReports() accepts.
     */
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

    /**
     * Limit the query to the Employees whose Work Entries, or Travel
     * Expenses, the given Employee may reopen through the reopen Permission
     * given: those its Scope covers, never themselves. Being someone's
     * Manager doesn't count, so without the Permission it's nobody.
     */
    public function reopenableBy(Employee $reopener, Permission $reopen): static
    {
        return $this->scoped(function () use ($reopener, $reopen): void {
            $scope = $reopener->widestScopeFor($reopen);

            if ($scope === null) {
                $this->whereRaw('false');

                return;
            }

            $scope->limit($this, $reopener);
            $this->whereKeyNot($reopener->id);
        });
    }

    /**
     * Limit the query to the Employees whose Work Entries the given Employee
     * may see, matching WorkEntryPolicy::viewAny().
     */
    public function withWorkEntriesVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $viewer->limitToReachOf($this, Permission::seeingWorkEntries());
        });
    }

    /**
     * Limit the query to the Employees whose travel the given Employee may
     * see across Employees, through the Permissions that show it
     * (Permission::seeingTravel()).
     */
    public function withTravelVisibleTo(Employee $viewer): static
    {
        return $this->scoped(function () use ($viewer): void {
            $viewer->limitToReachOf($this, Permission::seeingTravel());
        });
    }

    /**
     * Limit the query to Employees with something only their Manager can
     * settle: Pending Work Entries, Time Off, Travel Requests or Travel
     * Expenses to decide, or an Open Performance Evaluation to complete.
     */
    public function withWorkForTheirManager(): static
    {
        return $this->scoped(function (): void {
            $this->where(fn (Builder $pending) => $pending
                ->whereHas('workEntries', fn (Builder $entries) => $entries->where('work_entries.state', WorkEntryState::Pending))
                ->orWhereHas('timeOff', fn (Builder $timeOff) => $timeOff->where('state', TimeOffState::Pending))
                ->orWhereHas('travelRequests', fn (Builder $travelRequests) => $travelRequests->where('state', TravelRequestState::Pending))
                ->orWhereHas('travelRequests.travelExpenses', fn (Builder $expenses) => $expenses->where('travel_expenses.state', TravelExpenseState::Pending))
                ->orWhereHas('performanceEvaluations', fn (Builder $evaluations) => $evaluations->where('state', PerformanceEvaluationState::Open)));
        });
    }

    /**
     * Limit the query to Employees who work for the company on at least
     * one day of the calendar year: started by its end, and not left
     * before its start.
     */
    public function employedDuring(int $year): static
    {
        return $this->scoped(function () use ($year): void {
            $this->whereDate('start_date', '<=', "{$year}-12-31")
                ->where(fn (Builder $staying) => $staying->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', "{$year}-01-01"));
        });
    }

    /**
     * Limit the query to Employees who are current today.
     */
    public function current(): static
    {
        return $this->scoped(function (): void {
            $this->whereDate('start_date', '<=', today())->notFormer();
        });
    }

    /**
     * Limit the query to Employees who aren't Former today: no Leaving Date,
     * or one from today on. Future Employees are included.
     */
    public function notFormer(): static
    {
        return $this->scoped(function (): void {
            $this->where(fn (Builder $staying) => $staying->whereNull('leaving_date')->orWhereDate('leaving_date', '>=', today()));
        });
    }

    /**
     * Limit the query to Employees who are future, current or former today.
     */
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

    /**
     * Add each Employee's effective_manager_id: walking up from their stored
     * Manager, the first Employee who hasn't left, today or on the given day.
     * It is worked out when read, so nothing needs reassigning when a Manager
     * leaves. The walk only climbs past Managers who have left, so it ends at
     * the one row that's picked.
     */
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
