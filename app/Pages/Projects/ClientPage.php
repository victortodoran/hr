<?php

namespace App\Pages\Projects;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Support\TeamMember;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ClientPage
{
    /**
     * Only today's team, of Assignments the viewer may see; each Project's page keeps its full team.
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
                        ->map(fn (Assignment $assignment): array => TeamMember::of($assignment))
                        ->all(),
                ]),
        ];
    }
}
