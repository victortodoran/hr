<?php

namespace App\Support;

use App\Models\Assignment;

/**
 * One member of a Project's team as the Client's and the Project's pages
 * show them: their Assignment to the Project and whose it is.
 */
final class TeamMember
{
    /**
     * @return array{id: int, employee: array{id: int, name: string}, job_title: string|null, start_date: string, end_date: string|null, capacity: int}
     */
    public static function of(Assignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'employee' => $assignment->employee->only('id', 'name'),
            'job_title' => $assignment->job_title,
            'start_date' => $assignment->start_date->toDateString(),
            'end_date' => $assignment->end_date?->toDateString(),
            'capacity' => $assignment->capacity,
        ];
    }
}
