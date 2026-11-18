<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * Who may act on Performance Evaluations. Every Employee sees their own
 * with no Permission needed, and the Permissions for reading them
 * (Permission::seeingPerformanceEvaluations()) let others read them, never
 * write them. Only the Employee's effective Manager starts
 * one, gives, changes or removes its Goals, gives their Outcomes and
 * Completes it: no Role grants that, and
 * since nobody is their own effective Manager, nobody sets their own.
 * The Employee and their effective Manager both change its Goals'
 * Progress while it is Open.
 */
class PerformanceEvaluationPolicy
{
    /**
     * The Performance Evaluations page is for Employees who are the
     * effective Manager of anyone, Former Employees included, since a
     * Former Employee's Evaluation can still be written, and for anyone
     * one of the Permissions for reading them
     * (Permission::seeingPerformanceEvaluations()) reaches beyond
     * themselves.
     */
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

    /**
     * Only the Employee's effective Manager may start their Performance
     * Evaluation, and only for the current year, while the Employee works
     * for the company on some day of it and has none for it yet.
     */
    public function create(Employee $actor, Employee $employee): bool
    {
        $year = today()->year;

        return $this->isEffectiveManagerOf($actor, $employee)
            && Employee::query()->whereKey($employee->id)->employedDuring($year)->exists()
            && ! $employee->performanceEvaluations()->where('year', $year)->exists();
    }

    /**
     * Only the Employee's effective Manager may give, change or remove the
     * Goals of their Performance Evaluation, give their Outcomes and its
     * summary, and Complete it, and only while it is Open.
     */
    public function edit(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->state->isEditable()
            && $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee);
    }

    /**
     * Changing the Progress of its Goals: the Employee whose Evaluation it
     * is and their effective Manager, only while it is Open. No Role
     * grants it, so readers see the Progress without changing it.
     */
    public function changeProgress(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->state->isEditable()
            && ($performanceEvaluation->employee_id === $actor->id
                || $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee));
    }

    /**
     * Seeing the Outcomes and summary of a Performance Evaluation the actor
     * may open: its effective Manager always, while writing them, and
     * anyone else only once it is Completed.
     */
    public function seeOutcomes(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->isCompleted()
            || $this->isEffectiveManagerOf($actor, $performanceEvaluation->employee);
    }

    /**
     * Reopening a Completed Performance Evaluation, sending it back to Open
     * so its Manager can correct it: only someone whom reopen performance
     * evaluations reaches the Employee for. Being their Manager isn't
     * enough on its own, and nobody reopens their own.
     */
    public function reopen(Employee $actor, PerformanceEvaluation $performanceEvaluation): bool
    {
        return $performanceEvaluation->isCompleted()
            && $performanceEvaluation->employee_id !== $actor->id
            && $actor->reachesThrough([Permission::ReopenPerformanceEvaluations], $performanceEvaluation->employee);
    }

    /**
     * Whether the actor is the Employee's effective Manager, the one who
     * writes their Performance Evaluations.
     */
    private function isEffectiveManagerOf(Employee $actor, Employee $employee): bool
    {
        return Employee::query()->effectivelyManagedBy($actor)->whereKey($employee->id)->exists();
    }
}
