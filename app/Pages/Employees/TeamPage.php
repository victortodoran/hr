<?php

namespace App\Pages\Employees;

use App\Models\Employee;

/**
 * The Team page as the props it renders: a Manager's Direct Reports.
 */
final class TeamPage
{
    /**
     * The viewer's Direct Reports by name, with their internal Job Title
     * and Department, and whether the viewer may open each one's Employee
     * page.
     *
     * @return array{directReports: array<int, array{id: int, name: string, email: string, job_title: string|null, department: string|null, can_view: bool}>}
     */
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
