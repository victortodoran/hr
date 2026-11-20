<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Assignments\UpdateTrimmingAssignments;
use App\Actions\Projects\DeleteProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Requests\Search\NameSearchRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Pages\Projects\ProjectFormPage;
use App\Pages\Projects\ProjectListPage;
use App\Pages\Projects\ProjectPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(NameSearchRequest $request): Response
    {
        return Inertia::render('projects/Index', ProjectListPage::props($request->search()));
    }

    /** The form opens as a sheet over this page (rule L14), so it has a URL of its own. */
    public function create(NameSearchRequest $request): Response
    {
        return Inertia::render('projects/Index', ProjectFormPage::create($request->search()));
    }

    public function show(#[CurrentUser] Employee $actor, Project $project): Response
    {
        return Inertia::render('projects/Show', ProjectPage::props($actor, $project));
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', $project);
    }

    public function update(ProjectRequest $request, Project $project, UpdateTrimmingAssignments $updateTrimming): RedirectResponse
    {
        $updateTrimming->updateProject($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return to_route('projects.show', $project);
    }

    public function destroy(Project $project, DeleteProject $deleteProject): RedirectResponse
    {
        $deleteProject->delete($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
