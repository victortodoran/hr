<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * No Role grants writing them, and since nobody is their own effective
 * Manager, nobody writes their own.
 */
class PerformanceEvaluationPolicy
{
    /** Former Employees count, since a Former Employee's Evaluation can still be written. */
    public function viewList(Employee $actor): bool
    {
        return $actor->managesAnyone()
            || $actor->seesOthersPerformanceEvaluations();
    }

    /**
     * Seeing a Performance Evaluation on its own page: the Employee whose
     * Evaluation it is, their effective Manager, and whoever one of the
     * Permissions for reading them (Permission::seeingPerformanceEvaluations())
     * reaches the Employee for.
     */
    public function view(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->employee_id === $actor->id
            || $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee)
            || $actor->reachesThrough(Permission::seeingPerformanceEvaluations(), $performanceEvaluation->employee);
    }

    public function createAny(Employee $actor): bool
    {
        foreach (PerformanceEvaluation::creatableYears() as $year) {
            if (Employee::query()->awaitingPerformanceEvaluationFrom($actor, $year)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function create(Employee $actor, Employee $employee, int $year): bool
    {
        return Employee::query()->awaitingPerformanceEvaluationFrom($actor, $year)->whereKey($employee->id)->exists();
    }

    public function edit(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->state->isEditable()
            && $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee);
    }

    /** No Role grants it, so readers see the Progress without changing it. */
    public function changeProgress(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $this->takesPartWhileOpen($actor, $performanceEvaluation);
    }

    /** No Role grants it, so readers read the thread without writing in it. */
    public function comment(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $this->takesPartWhileOpen($actor, $performanceEvaluation);
    }

    /**
     * Anyone but the effective Manager sees them only once Completed, while the
     * Manager may still be writing them.
     */
    public function seeOutcomes(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->isCompleted()
            || $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee);
    }

    /** Being their Manager isn't enough on its own, and nobody reopens their own. */
    public function reopen(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->isCompleted()
            && $performanceEvaluation->employee_id !== $actor->id
            && $actor->reachesThrough([Permission::ReopenPerformanceEvaluations], $performanceEvaluation->employee);
    }

    private function takesPartWhileOpen(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->state->isEditable()
            && ($performanceEvaluation->employee_id === $actor->id
                || $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee));
    }

    private function isEffectiveManagerOf(Employee $actor, Employee $employee): bool
    {
        return Employee::query()->effectivelyManagedBy($actor)->whereKey($employee->id)->exists();
    }
}
