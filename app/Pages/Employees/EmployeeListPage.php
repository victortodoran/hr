<?php

namespace App\Pages\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Department;
use App\Models\Employee;

final class EmployeeListPage
{
    /**
     * @param  array{search: string, status: EmploymentStatus|null, department: int|null, manager: Employee|null}  $filters
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, array $filters): array
    {
        $matching = self::visibleTo($actor)
            ->whereNameContains($filters['search'])
            ->when($filters['department'], fn (EmployeeBuilder $query, int $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['manager'], fn (EmployeeBuilder $query, Employee $manager) => $query->effectivelyManagedBy($manager));

        $employees = $matching->clone()
            ->when($filters['status'], fn (EmployeeBuilder $query, EmploymentStatus $status) => $query->inEmploymentStatus($status))
            ->with(['department:id,name', 'effectiveManager:id,name', 'internalAssignment:id,employee_id,project_id,job_title'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->internalAssignment?->job_title,
                'status' => $employee->employmentStatus()->value,
                'start_date' => $employee->start_date->toDateString(),
                'department' => $employee->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                'manager' => $employee->effectiveManager?->only('id', 'name'),
            ]);

        $counts = collect(EmploymentStatus::cases())
            ->mapWithKeys(fn (EmploymentStatus $each): array => [$each->value => $matching->clone()->inEmploymentStatus($each)->count()]);

        return [
            'employees' => $employees,
            'search' => $filters['search'],
            'status' => $filters['status']?->value,
            'department' => $filters['department'],
            'manager' => $filters['manager']?->id,
            'counts' => ['all' => $counts->sum(), ...$counts->all()],
            'departments' => Department::query()
                ->whereIn('id', self::visibleTo($actor)->toBase()->select('department_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'managers' => Employee::query()
                ->whereIn('id', self::visibleTo($actor)->toBase()->select('effective_manager_id'))
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name']),
            'canAdd' => $actor->can('create', Employee::class),
        ];
    }

    /** Selects effective_manager_id as a column, so the query can filter on it. */
    private static function visibleTo(Employee $actor): EmployeeBuilder
    {
        $visible = Employee::query()
            ->withRecordVisibleTo($actor)
            ->withEffectiveManager();

        return Employee::query()->fromSub($visible, 'employees');
    }
}
