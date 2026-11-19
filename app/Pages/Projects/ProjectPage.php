<?php

namespace App\Pages\Projects;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Support\TeamMember;

/**
 * A Project's page as the props it renders: the Project and its team, and
 * the Clients it can be for when the viewer may change it there.
 */
final class ProjectPage
{
    /**
     * The Project and its team: the Assignments to it that the viewer may
     * see, by Employee and then from the earliest. Whether the viewer may
     * change or delete it, and if so the Clients it can be for.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, Project $project): array
    {
        $canManage = $actor->can('update', $project);

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->only('id', 'name'),
                ...$project->dates(),
                'is_internal' => $project->is_internal,
            ],
            'team' => $project->assignments()
                ->visibleTo($actor)
                ->with('employee:id,name')
                ->orderByEmployeeName()
                ->get()
                ->map(fn (Assignment $assignment): array => TeamMember::of($assignment)),
            'canManage' => $canManage,
            'clients' => $canManage ? ProjectFormPage::clients() : [],
        ];
    }
}
