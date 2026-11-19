<?php

namespace App\Http\Controllers\Projects;

use App\Actions\DeleteProject;
use App\Actions\UpdateTrimmingAssignments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Pages\Projects\ProjectFormPage;
use App\Pages\Projects\ProjectListPage;
use App\Pages\Projects\ProjectPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets Employees see the company's Projects, and those who manage Projects
 * create, edit and delete them. The Internal Project is listed but fixed.
 */
class ProjectController extends Controller
{
    /**
     * Show every Project, the Internal Project first and the rest by name.
     */
    public function index(): Response
    {
        return Inertia::render('projects/Index', ProjectListPage::props());
    }

    /**
     * Show the form for adding a Project, with the Clients it can be for.
     */
    public function create(): Response
    {
        return Inertia::render('projects/Create', ProjectFormPage::create());
    }

    /**
     * Show a Project and its team: the Assignments to it that the viewer may
     * see, by Employee and then from the earliest. Whoever may change it
     * does so here.
     */
    public function show(#[CurrentUser] Employee $actor, Project $project): Response
    {
        return Inertia::render('projects/Show', ProjectPage::props($actor, $project));
    }

    /**
     * Add a Project and open its page.
     */
    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }

    /**
     * Change a Project's name, dates or Client. Its Assignments are trimmed
     * to end by its end date in the same transaction. Saving stays on the
     * Project's page.
     */
    public function update(ProjectRequest $request, Project $project, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateProject($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return to_route('projects.show', $project);
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
