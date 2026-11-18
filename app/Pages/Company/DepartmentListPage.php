<?php

namespace App\Pages\Company;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Departments page as the props it renders: the company's flat list of
 * Departments.
 */
final class DepartmentListPage
{
    /**
     * Every Department, by name.
     *
     * @return array{departments: Collection<int, Department>}
     */
    public static function props(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
