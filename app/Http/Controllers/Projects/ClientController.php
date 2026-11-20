<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\DeleteClient;
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

class ClientController extends Controller
{
    public function index(NameSearchRequest $request): Response
    {
        return Inertia::render('clients/Index', ClientListPage::props($request->search()));
    }

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

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client renamed.')]);

        return back();
    }

    public function destroy(Client $client, DeleteClient $deleteClient): RedirectResponse
    {
        $deleteClient->delete($client);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
