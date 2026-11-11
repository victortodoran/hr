<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets Admins keep the list of Clients the company does work for. A company
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
     * Create a Client.
     */
    public function store(ClientRequest $request): RedirectResponse
    {
        Client::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.index');
    }

    /**
     * Rename a Client.
     */
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
