<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Saves an Employee or a Project and, in the same transaction, trims its
 * Assignments to end by its last day (a Leaving Date or a Project end date),
 * so nobody appears to work after leaving or on a finished Project.
 * Validation has already refused a last day before any Assignment starts.
 */
class UpdateTrimmingAssignments
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateEmployee(Employee $employee, array $changes): void
    {
        DB::transaction(function () use ($employee, $changes): void {
            $employee->update($changes);
            $this->trim($employee->assignments(), $employee->leaving_date);
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateProject(Project $project, array $changes): void
    {
        DB::transaction(function () use ($project, $changes): void {
            $project->update($changes);
            $this->trim($project->assignments(), $project->end_date);
        });
    }

    /**
     * @param  HasMany<Assignment, covariant Employee|Project>  $assignments
     */
    private function trim(HasMany $assignments, ?CarbonInterface $lastDay): void
    {
        if ($lastDay === null) {
            return;
        }

        $assignments
            ->where(fn (Builder $running) => $running->whereNull('end_date')->orWhereDate('end_date', '>', $lastDay))
            ->update(['end_date' => $lastDay->toDateString()]);
    }
}
