<?php

namespace App\Pages\Travel;

use App\Models\Assignment;
use App\Models\Employee;

/**
 * The form for making a Travel Request as the props it renders.
 */
final class TravelRequestFormPage
{
    /**
     * Today and the Assignments the Employee can still travel on, those not
     * ended before today, by Project name with their dates.
     *
     * @return array{today: string, assignments: array<int, array{id: int, project: string, client: string|null, start_date: string, end_date: string|null}>}
     */
    public static function create(Employee $actor): array
    {
        return [
            'today' => today()->toDateString(),
            'assignments' => $actor->assignments()
                ->notEndedBefore(today())
                ->with('project.client:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->get()
                ->sortBy('project.name')
                ->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id,
                    'project' => $assignment->project->name,
                    'client' => $assignment->project->client?->name,
                    'start_date' => $assignment->start_date->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
