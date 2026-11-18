<?php

namespace App\Pages\Projects;

use App\Models\Client;
use App\Models\Project;

/**
 * The Projects page as the props it renders: the company's Projects, the
 * Internal Project among them.
 */
final class ProjectListPage
{
    /**
     * Every Project, the Internal Project first and the rest by name, with
     * the Clients a Project can be for when the viewer may manage Projects.
     *
     * @return array<string, mixed>
     */
    public static function props(bool $canManage): array
    {
        return [
            'projects' => Project::query()
                ->with('client:id,name')
                ->orderByDesc('is_internal')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client?->only('id', 'name'),
                    ...$project->dates(),
                    'is_internal' => $project->is_internal,
                ]),
            'clients' => $canManage ? Client::query()->orderBy('name')->get(['id', 'name']) : [],
        ];
    }
}
