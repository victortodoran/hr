<?php

namespace App\Pages\Travel;

use App\Models\Assignment;
use App\Models\Employee;

final class TravelRequestFormPage
{
    /** @return array<string, mixed> */
    public static function create(Employee $actor): array
    {
        return [
            ...TravelPage::props($actor),
            'requesting' => true,
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
