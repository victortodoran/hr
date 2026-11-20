<?php

namespace App\Pages\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

final class EmployeeFormPage
{
    /** @return array{departments: Collection<int, Department>, managers: array<int, array{id: int, name: string, status: string}>} */
    public static function create(): array
    {
        return self::choices(null);
    }

    /** @return array<string, mixed> */
    public static function edit(Employee $actor, Employee $employee): array
    {
        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'department_id' => $employee->department_id,
                'manager_id' => $employee->manager_id,
                'start_date' => $employee->start_date->toDateString(),
                'leaving_date' => $employee->leaving_date?->toDateString(),
                'status' => $employee->employmentStatus()->value,
                'can_resend_invitation' => $actor->can('resendInvitation', $employee),
            ],
            'reportsNeedingAManager' => EmployeePage::reportsNeedingAManager($employee),
            ...self::choices($employee),
        ];
    }

    /**
     * An Employee's current Manager stays on offer after leaving, so the record can still be saved.
     *
     * @return array{departments: Collection<int, Department>, managers: array<int, array{id: int, name: string, status: string}>}
     */
    private static function choices(?Employee $employee): array
    {
        $managers = Employee::query()
            ->orderBy('name')
            ->get(['id', 'name', 'start_date', 'leaving_date'])
            ->reject(fn (Employee $manager): bool => $manager->is($employee))
            ->filter(fn (Employee $manager): bool => $manager->employmentStatus() !== EmploymentStatus::Former
                || $manager->id === $employee?->manager_id);

        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'managers' => $managers
                ->map(fn (Employee $manager): array => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'status' => $manager->employmentStatus()->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
