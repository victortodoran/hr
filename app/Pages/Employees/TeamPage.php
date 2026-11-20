<?php

namespace App\Pages\Employees;

use App\Models\Employee;

final class TeamPage
{
    /** @return array{directReports: array<int, array{id: int, name: string, email: string, job_title: string|null, department: string|null, can_view: bool}>} */
    public static function props(Employee $actor): array
    {
        return [
            'directReports' => $actor->managedEmployees()
                ->map(fn (Employee $report): array => [
                    'id' => $report->id,
                    'name' => $report->name,
                    'email' => $report->email,
                    'job_title' => $report->internalAssignment?->job_title,
                    'department' => $report->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                    'can_view' => $actor->can('view', $report),
                ])
                ->all(),
        ];
    }
}
