<?php

use App\Enums\Permission;
use App\Http\Controllers\Projects\ClientController;
use App\Http\Requests\Projects\ClientRequest;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(ClientController::class, ClientRequest::class, Client::class);

function clientManager(): Employee
{
    return employeeWith([Permission::ManageClients->value => null]);
}

it('lists the Clients by name, with how many Projects each has', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    $acme = Client::factory()->create(['name' => 'Acme']);
    Project::factory()->count(2)->forClient($globex)->create();

    actingAs(clientManager())->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Index')
            ->where('clients.data', [
                ['id' => $acme->id, 'name' => 'Acme', 'projects_count' => 0],
                ['id' => $globex->id, 'name' => 'Globex', 'projects_count' => 2],
            ]),
        );
});

it('shows an empty Clients page to a company that has none', function () {
    actingAs(clientManager())->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->where('clients.data', []));
});

it('searches the Clients by any part of a name, ignoring case and treating % and _ as ordinary characters', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    $odd = Client::factory()->create(['name' => 'Initech 100%']);
    Client::factory()->create(['name' => 'Initech 1000']);

    actingAs(clientManager())->get(route('clients.index', ['search' => 'GLOB']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'GLOB')
            ->has('clients.data', 1)->where('clients.data.0.id', $globex->id),
        );

    actingAs(clientManager())->get(route('clients.index', ['search' => '0%']))
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.id', $odd->id));
});

it('lists the Clients 25 at a time', function () {
    Client::factory()->count(26)->create();

    actingAs(clientManager())->get(route('clients.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.total', 26),
        );
});

it('rejects a Client search that is not text or is too long', function (mixed $search, string $message) {
    actingAs(clientManager())
        ->get(route('clients.index', ['search' => $search]))
        ->assertSessionHasErrors(['search' => $message]);
})->with([
    'not text' => [['Acme'], 'The search field must be a string.'],
    'too long' => [str_repeat('a', 256), 'The search field must not be greater than 255 characters.'],
]);

it('lets a Client manager create a Client', function () {
    actingAs(clientManager())
        ->post(route('clients.store'), ['name' => 'Globex'])
        ->assertRedirect(route('clients.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Client created.']);

    expect(Client::query()->where('name', 'Globex')->exists())->toBeTrue();
});

it('rejects a Client name that is missing, too long or already taken', function (mixed $name, string $message) {
    Client::factory()->create(['name' => 'Acme']);

    actingAs(clientManager())
        ->post(route('clients.store'), ['name' => $name])
        ->assertSessionHasErrors(['name' => $message]);
})->with([
    'missing' => ['', 'The name field is required.'],
    'not text' => [['Globex'], 'The name field must be a string.'],
    'too long' => [str_repeat('a', 256), 'The name field must not be greater than 255 characters.'],
    'taken' => ['Acme', 'The name has already been taken.'],
]);

it('lets a Client manager rename a Client from its page, keeping its Projects', function () {
    $acme = Client::factory()->create(['name' => 'Acme']);
    $project = Project::factory()->forClient($acme)->create();

    actingAs(clientManager())
        ->from(route('clients.show', $acme))
        ->patch(route('clients.update', $acme), ['name' => 'Acme Corp'])
        ->assertRedirect(route('clients.show', $acme))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Client renamed.']);

    expect($acme->refresh()->name)->toBe('Acme Corp')
        ->and($project->refresh()->client_id)->toBe($acme->id);
});

it('lets a Client be saved under its current name, but not another Client\'s', function () {
    $acme = Client::factory()->create(['name' => 'Acme']);
    Client::factory()->create(['name' => 'Globex']);

    actingAs(clientManager())
        ->patch(route('clients.update', $acme), ['name' => 'Acme'])
        ->assertSessionHasNoErrors();

    actingAs(clientManager())
        ->patch(route('clients.update', $acme), ['name' => 'Globex'])
        ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

    expect($acme->refresh()->name)->toBe('Acme');
});

it('lets a Client manager delete a Client with no Projects', function () {
    $acme = Client::factory()->create();

    actingAs(clientManager())
        ->delete(route('clients.destroy', $acme))
        ->assertRedirect(route('clients.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Client deleted.']);

    expect(Client::query()->whereKey($acme->id)->exists())->toBeFalse();
});

it('refuses to delete a Client that still has Projects, and says why', function () {
    $acme = Client::factory()->create(['name' => 'Acme']);
    Project::factory()->forClient($acme)->create();

    actingAs(clientManager())
        ->delete(route('clients.destroy', $acme))
        ->assertSessionHasErrors(['client' => 'Acme still has Projects. Move or delete them before deleting the Client.']);

    expect(Client::query()->whereKey($acme->id)->exists())->toBeTrue();
});

it('refuses every Client action to an Employee who does not manage Clients', function (string $method, Closure $url) {
    $acme = Client::factory()->create(['name' => 'Acme']);

    actingAs(employeeWith([Permission::ManageProjects->value => null, Permission::ViewProjects->value => null]))
        ->{$method}($url($acme), ['name' => 'Initech'])
        ->assertForbidden();

    expect(Client::query()->where('name', 'Initech')->exists())->toBeFalse()
        ->and($acme->fresh()?->name)->toBe('Acme');
})->with([
    'list' => ['get', fn (Client $client): string => route('clients.index')],
    'create' => ['post', fn (Client $client): string => route('clients.store')],
    'rename' => ['patch', fn (Client $client): string => route('clients.update', $client)],
    'delete' => ['delete', fn (Client $client): string => route('clients.destroy', $client)],
]);
