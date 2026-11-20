<?php

namespace App\Pages\Company;

use App\Models\Builders\EmployeeBuilder;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Departments page as the props it renders: the company's flat list of
 * Departments.
 */
final class DepartmentListPage
{
    /**
     * Every Department, by name. For a viewer who may list Employees, each
     * one carries how many of its Employees, of any status, the viewer may
     * see the record of, which is what the Employees list filtered to it
     * shows; for anyone else the counts are left out.
     *
     * @return array{departments: Collection<int, Department>, showsEmployeeCounts: bool}
     */
    public static function props(Employee $actor): array
    {
        $showsEmployeeCounts = $actor->can('viewAny', Employee::class);

        return [
            'departments' => Department::query()
                ->select(['id', 'name'])
                ->when($showsEmployeeCounts, fn (Builder $query) => $query->withCount([
                    'employees as employee_count' => fn (EmployeeBuilder $employees) => $employees->withRecordVisibleTo($actor),
                ]))
                ->orderBy('name')
                ->get(),
            'showsEmployeeCounts' => $showsEmployeeCounts,
        ];
    }
}
