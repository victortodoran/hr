<?php

namespace App\Pages\Projects;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Support\TeamMember;

final class ProjectPage
{
    /** @return array<string, mixed> */
    public static function props(Employee $actor, Project $project): array
    {
        $canManage = $actor->can('update', $project);
        $runningToday = $project->assignments()->visibleTo($actor)->runningOn(today());

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
            'teamToday' => [
                'employees' => (clone $runningToday)->distinct()->count('employee_id'),
                'capacity' => (int) $runningToday->sum('capacity'),
            ],
            'canManage' => $canManage,
            'clients' => $canManage ? ProjectFormPage::clients() : [],
        ];
    }
}
