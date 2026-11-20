<?php

namespace App\Pages\Travel;

use App\Models\Assignment;
use App\Models\Employee;

/**
 * The Travel page with the sheet for making a Travel Request open over it
 * (rule L14), as the props it renders.
 */
final class TravelRequestFormPage
{
    /**
     * The Travel page's props (TravelPage), that the sheet is open, today,
     * and the Assignments the Employee can still travel on, those not ended
     * before today, by Project name with their dates.
     *
     * @return array<string, mixed>
     */
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
