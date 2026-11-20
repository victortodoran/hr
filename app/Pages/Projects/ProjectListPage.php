<?php

namespace App\Pages\Projects;

use App\Models\Project;

final class ProjectListPage
{
    /** @return array<string, mixed> */
    public static function props(string $search): array
    {
        return [
            'projects' => Project::query()
                ->with('client:id,name')
                ->whereNameContains($search)
                ->orderByDesc('is_internal')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client?->only('id', 'name'),
                    ...$project->dates(),
                    'is_internal' => $project->is_internal,
                ]),
            'search' => $search,
        ];
    }
}
