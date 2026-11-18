<?php

namespace App\Http\Controllers\Projects;

use App\Actions\DeleteProject;
use App\Actions\UpdateTrimmingAssignments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Pages\Projects\ProjectListPage;
use App\Pages\Projects\ProjectPage;
use Illuminate\Container\Attributes\CurrentUser;
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
        return Inertia::render('projects/Index', ProjectListPage::props(Gate::allows('create', Project::class)));
    }

    /**
     * Show a Project and its team: the Assignments to it that the viewer may
     * see, by Employee and then from the earliest.
     */
    public function show(#[CurrentUser] Employee $actor, Project $project): Response
    {
        return Inertia::render('projects/Show', ProjectPage::props($actor, $project));
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        Project::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    /**
     * Change a Project's name, dates or Client. Its Assignments are trimmed
     * to end by its end date in the same transaction.
     */
    public function update(ProjectRequest $request, Project $project, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateProject($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return to_route('projects.index');
    }

    /**
     * Delete a Project, refusing once anyone has been assigned to it, since
     * that work is part of their record.
     */
    public function destroy(Project $project, DeleteProject $deleteProject): RedirectResponse
    {
        $deleteProject->delete($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
