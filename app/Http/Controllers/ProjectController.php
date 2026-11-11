<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets Employees see the company's Projects, and those who manage Projects
 * create, edit and delete them. The Internal Project is listed but fixed.
 */
class ProjectController extends Controller
{
    /**
     * Show every Project, the Internal Project first and the rest by name,
     * with the Clients a Project can be for when the viewer may manage them.
     */
    public function index(): Response
    {
        $projects = Project::query()
            ->with('client:id,name')
            ->orderByDesc('is_internal')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->only('id', 'name'),
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'is_internal' => $project->is_internal,
            ]);

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'clients' => Gate::allows('create', Project::class)
                ? Client::query()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * Create a Project.
     */
    public function store(ProjectRequest $request): RedirectResponse
    {
        Project::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    /**
     * Change a Project's name, dates or Client.
     */
    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return to_route('projects.index');
    }

    /**
     * Delete a Project.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
