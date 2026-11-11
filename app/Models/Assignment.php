<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Scope;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope as QueryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Employee working on a Project for a period of time, with a Job Title
 * and a share of their time. It always falls within the Employee's working
 * period and the Project's dates.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $project_id
 * @property string $job_title
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int|null $share A whole percentage of the Employee's time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Project $project
 */
#[Fillable(['project_id', 'job_title', 'start_date', 'end_date', 'share'])] // @pest-mutate-ignore (class attributes never show as covered)
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Limit the query to the Assignments the Employee may see: those of the
     * Employees covered by their view assignments Scope or their manage
     * assignments Scope, matching AssignmentPolicy::viewAny().
     *
     * @param  Builder<Assignment>  $query
     */
    #[QueryScope]
    protected function visibleTo(Builder $query, Employee $actor): void
    {
        $scopes = collect([Permission::ViewAssignments, Permission::ManageAssignments])
            ->map(fn (Permission $permission): ?Scope => $actor->widestScopeFor($permission))
            ->filter();

        $query->whereHas('employee', fn (Builder $employees) => $employees->where(function (Builder $covered) use ($scopes, $actor): void {
            $covered->whereRaw('false');
            $scopes->each(fn (Scope $scope) => $covered->orWhere(fn (Builder $employee) => $scope->limit($employee, $actor)));
        }));
    }

    /**
     * Order Assignments by their Employee's name, then from the earliest.
     *
     * @param  Builder<Assignment>  $query
     */
    #[QueryScope]
    protected function orderByEmployeeName(Builder $query): void
    {
        $query->orderBy(Employee::query()->select('name')->whereColumn('employees.id', 'assignments.employee_id'))
            ->orderBy('employee_id')
            ->orderBy('start_date')
            ->orderBy('id');
    }
}
