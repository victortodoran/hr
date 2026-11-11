<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets Employees see the Assignments their Scope covers, and those who
 * manage Assignments put Employees on Projects and change their Assignments.
 */
class AssignmentController extends Controller
{
    /**
     * Show an Employee's Assignments, earliest first, and when the viewer
     * may manage them, the Projects that are still open to Assignments.
     */
    public function index(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        $canManage = $actor->can('create', [Assignment::class, $employee]);

        return Inertia::render('assignments/Index', [
            'employee' => $employee->only('id', 'name'),
            'assignments' => $employee->assignments()
                ->with('project.client')
                ->orderBy('start_date')
                ->orderBy('id')
                ->get()
                ->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id,
                    'project' => [
                        'id' => $assignment->project->id,
                        'name' => $assignment->project->name,
                        'client' => $assignment->project->client?->name,
                    ],
                    'job_title' => $assignment->job_title,
                    'start_date' => $assignment->start_date->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                    'share' => $assignment->share,
                ]),
            'canManage' => $canManage,
            'projects' => $canManage ? $this->openProjects() : [],
        ]);
    }

    /**
     * Put the Employee on a Project.
     */
    public function store(AssignmentRequest $request, Employee $employee): RedirectResponse
    {
        $employee->assignments()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment created.')]);

        return to_route('employees.assignments.index', $employee);
    }

    /**
     * Change an Assignment's Job Title, dates or share, including ending it
     * early.
     */
    public function update(AssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        $assignment->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment saved.')]);

        return to_route('employees.assignments.index', $assignment->employee_id);
    }

    /**
     * The Projects an Employee can be put on: every Project except the
     * Internal Project and those that have finished, by name.
     *
     * @return array<int, array{id: int, name: string, client: string|null, start_date: string|null, end_date: string|null}>
     */
    private function openProjects(): array
    {
        return Project::query()
            ->with('client:id,name')
            ->where('is_internal', false)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->name,
                'start_date' => $project->start_date?->toDateString(), // @pest-mutate-ignore (only the Internal Project has no start date, and it is left out)
                'end_date' => $project->end_date?->toDateString(),
            ])
            ->all();
    }
}
