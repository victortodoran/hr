<?php

use App\Enums\Permission;
use App\Http\Controllers\Projects\ClientController;
use App\Models\Client;
use App\Models\Project;

use function Pest\Laravel\actingAs;

covers(ClientController::class);

it('renames a Client and shows why one with Projects can\'t be deleted', function () {
    $acme = Client::factory()->create(['name' => 'Acme']);
    Project::factory()->for($acme)->create();

    actingAs(employeeWith([Permission::ManageClients->value => null, Permission::ViewProjects->value => null]));

    visit(route('clients.index'))
        ->click('Rename')
        ->assertValue("#client-name-{$acme->id}", 'Acme')
        ->fill("#client-name-{$acme->id}", 'Acme Group')
        ->click('Save')
        ->assertSee('Acme Group')
        ->click('Delete')
        ->assertSee('Delete Acme Group?')
        ->assertSee('Only a Client with no Projects can be deleted.')
        ->click('internal:role=dialog >> internal:role=button[name="Delete"s]')
        ->assertSee('Acme Group still has Projects. Move or delete them before deleting the Client.')
        ->assertNoJavaScriptErrors();

    expect($acme->refresh()->name)->toBe('Acme Group');
});
