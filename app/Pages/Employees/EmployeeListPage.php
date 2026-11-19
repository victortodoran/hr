<?php

namespace App\Pages\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;

/**
 * The Employees page as the props it renders: the Employees whose record
 * the viewer may see, a page at a time.
 */
final class EmployeeListPage
{
    /**
     * The Employees whose record the viewer may see, by name, with each
     * one's status, optionally narrowed to one status or part of a name,
     * and whether the viewer may add an Employee.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, string $search, ?EmploymentStatus $status): array
    {
        $employees = Employee::query()
            ->where(fn (EmployeeBuilder $query) => $actor->limitToReachOf($query, [Permission::ViewEmployeeRecords]))
            ->when($status, fn (EmployeeBuilder $query, EmploymentStatus $status) => $query->inEmploymentStatus($status))
            ->whereLike('name', '%'.addcslashes($search, '\\%_').'%')
            ->withEffectiveManager()
            ->with(['department:id,name', 'effectiveManager:id,name'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'status' => $employee->employmentStatus()->value,
                'department' => $employee->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                'manager' => $employee->effectiveManager?->only('id', 'name'),
            ]);

        return [
            'employees' => $employees,
            'search' => $search,
            'status' => $status?->value,
            'canAdd' => $actor->can('create', Employee::class),
        ];
    }
}
