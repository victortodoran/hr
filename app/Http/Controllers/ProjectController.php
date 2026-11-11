<?php

namespace App\Http\Controllers;

use App\Actions\CutOffAssignments;
use App\Http\Requests\ProjectRequest;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
     * Show a Project and its team: the Assignments to it that the viewer may
     * see, by Employee and then from the earliest.
     */
    public function show(#[CurrentUser] Employee $actor, Project $project): Response
    {
        return Inertia::render('projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->only('id', 'name'),
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'is_internal' => $project->is_internal,
            ],
            'team' => $project->assignments()
                ->visibleTo($actor)
                ->with('employee:id,name')
                ->orderByEmployeeName()
                ->get()
                ->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id,
                    'employee' => $assignment->employee->only('id', 'name'),
                    'job_title' => $assignment->job_title,
                    'start_date' => $assignment->start_date->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                    'share' => $assignment->share,
                ]),
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
     * Change a Project's name, dates or Client. Its Assignments are cut off
     * at its end date in the same transaction.
     */
    public function update(ProjectRequest $request, Project $project, CutOffAssignments $cutOff): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $cutOff): void {
            $project->update($request->validated());

            if ($project->end_date !== null) {
                $cutOff->at($project, $project->end_date);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return to_route('projects.index');
    }

    /**
     * Delete a Project, refusing once anyone has been assigned to it, since
     * that work is part of their record.
     */
    public function destroy(Project $project): RedirectResponse
    {
        if ($project->assignments()->exists()) {
            throw ValidationException::withMessages([
                'project' => __(':project has Assignments, so it can\'t be deleted. Set an end date instead.', ['project' => $project->name]),
            ]);
        }

        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
