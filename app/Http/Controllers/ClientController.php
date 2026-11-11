<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets those who manage Clients keep the list of Clients the company does work for. A company
 * that works only for itself never needs one.
 */
class ClientController extends Controller
{
    /**
     * Show every Client by name, with how many Projects each has.
     */
    public function index(): Response
    {
        return Inertia::render('clients/Index', [
            'clients' => Client::query()->select(['id', 'name'])->withCount('projects')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show a Client's Projects by name, empty ones included, each with the
     * Employees assigned to it today whose Assignments the viewer may see.
     * Each Project's page keeps its full team.
     */
    public function show(#[CurrentUser] Employee $actor, Client $client): Response
    {
        return Inertia::render('clients/Show', [
            'client' => $client->only('id', 'name'),
            'projects' => $client->projects()
                ->with(['assignments' => fn (HasMany $assignments) => $assignments
                    ->runningOn(today())
                    ->visibleTo($actor)
                    ->with('employee:id,name') // @pest-mutate-ignore (eager loading only saves queries)
                    ->orderByEmployeeName()])
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    ...$project->dates(),
                    'team' => $project->assignments
                        ->map(fn (Assignment $assignment): array => [
                            'id' => $assignment->id,
                            'employee' => $assignment->employee->only('id', 'name'),
                            'job_title' => $assignment->job_title,
                            'start_date' => $assignment->start_date->toDateString(),
                            'end_date' => $assignment->end_date?->toDateString(),
                            'capacity' => $assignment->capacity,
                        ])
                        ->all(),
                ]),
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.index');
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client renamed.')]);

        return to_route('clients.index');
    }

    /**
     * Delete a Client, refusing while it still has Projects.
     */
    public function destroy(Client $client): RedirectResponse
    {
        if ($client->projects()->exists()) {
            throw ValidationException::withMessages([
                'client' => __(':client still has Projects. Move or delete them before deleting the Client.', [
                    'client' => $client->name,
                ]),
            ]);
        }

        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
