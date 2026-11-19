<?php

namespace App\Pages\Projects;

use App\Models\Project;

/**
 * The Projects page as the props it renders: the company's Projects a page
 * at a time, the Internal Project among them.
 */
final class ProjectListPage
{
    /**
     * The Projects, the Internal Project first and the rest by name,
     * optionally searched by part of a name.
     *
     * @return array<string, mixed>
     */
    public static function props(string $search): array
    {
        return [
            'projects' => Project::query()
                ->with('client:id,name')
                ->whereLike('name', '%'.addcslashes($search, '\\%_').'%')
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
