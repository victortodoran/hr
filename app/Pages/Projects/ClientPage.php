<?php

namespace App\Pages\Projects;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Client's page as the props it renders: its Projects and who works on
 * them today.
 */
final class ClientPage
{
    /**
     * The Client's Projects by name, empty ones included, each with the
     * Employees assigned to it today whose Assignments the viewer may see.
     * Each Project's page keeps its full team.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, Client $client): array
    {
        return [
            'client' => $client->only('id', 'name'),
            'projects' => $client->projects()
                ->with(['assignments' => fn (HasMany $assignments) => $assignments
                    ->runningOn(today())
                    ->visibleTo($actor)
                    ->with('employee:id,name') // @pest-mutate-ignore (eager loading only saves queries)
                    ->orderByEmployeeName()])
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    ...$project->dates(),
                    'team' => $project->assignments
                        ->map(fn (Assignment $assignment): array => [
                            'id' => $assignment->id,
                            'employee' => $assignment->employee->only('id', 'name'),
                            'job_title' => $assignment->job_title,
                            'start_date' => $assignment->start_date->toDateString(),
                            'end_date' => $assignment->end_date?->toDateString(),
                            'capacity' => $assignment->capacity,
                        ])
                        ->all(),
                ]),
        ];
    }
}
