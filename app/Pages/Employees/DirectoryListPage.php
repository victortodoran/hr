<?php

namespace App\Pages\Employees;

use App\Models\Employee;

final class DirectoryListPage
{
    /** @return array<string, mixed> */
    public static function props(string $search): array
    {
        return [
            'employees' => Employee::query()
                ->current()
                ->withEffectiveManager()
                ->with(['department:id,name', 'effectiveManager:id,name', 'internalAssignment:id,employee_id,project_id,job_title'])
                ->whereNameContains($search)
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'job_title' => $employee->internalAssignment?->job_title,
                    'department' => $employee->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                    'manager' => $employee->effectiveManager?->only('id', 'name'),
                ]),
            'search' => $search,
        ];
    }
}
