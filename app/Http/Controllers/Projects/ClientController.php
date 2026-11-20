<?php

namespace App\Http\Controllers\Projects;

use App\Actions\DeleteClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ClientRequest;
use App\Http\Requests\Search\NameSearchRequest;
use App\Models\Client;
use App\Models\Employee;
use App\Pages\Projects\ClientListPage;
use App\Pages\Projects\ClientPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets those who manage Clients keep the list of Clients the company does work for. A company
 * that works only for itself never needs one.
 */
class ClientController extends Controller
{
    /**
     * Show the Clients a page at a time by name, with how many Projects each
     * has, optionally searched by part of a name.
     */
    public function index(NameSearchRequest $request): Response
    {
        return Inertia::render('clients/Index', ClientListPage::props($request->search()));
    }

    /**
     * Show a Client's Projects by name, empty ones included, each with the
     * Employees assigned to it today whose Assignments the viewer may see.
     * Each Project's page keeps its full team.
     */
    public function show(#[CurrentUser] Employee $actor, Client $client): Response
    {
        return Inertia::render('clients/Show', ClientPage::props($actor, $client));
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.index');
    }

    /**
     * Rename a Client, back on the page it was renamed from: the Clients or
     * the Client's own page.
     */
    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client renamed.')]);

        return back();
    }

    /**
     * Delete a Client, refusing while it still has Projects.
     */
    public function destroy(Client $client, DeleteClient $deleteClient): RedirectResponse
    {
        $deleteClient->delete($client);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
