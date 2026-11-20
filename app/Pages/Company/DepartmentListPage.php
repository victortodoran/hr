<?php

namespace App\Pages\Company;

use App\Models\Builders\EmployeeBuilder;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class DepartmentListPage
{
    /**
     * Each count is of the Employees the viewer may see the record of, so it matches the Employees list filtered to that Department.
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
