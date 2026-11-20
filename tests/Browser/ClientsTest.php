<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Projects\ClientController;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;

use function Pest\Laravel\actingAs;

covers(ClientController::class);

it('adds a Client in a dialog, renames it from its ⋯ menu, and shows why one with Projects can\'t be deleted', function () {
    $acme = Client::factory()->create(['name' => 'Acme']);
    Project::factory()->for($acme)->create();

    actingAs(employeeWith([Permission::ManageClients->value => null, Permission::ViewProjects->value => null]));

    visit(route('clients.index'))
        ->click('internal:role=button[name="Add Client…"s]')
        ->fill('#client-name', 'Globex')
        ->click('internal:role=dialog >> internal:role=button[name="Add Client"s]')
        ->assertSee('Client created.')
        ->assertSee('Globex')
        ->hover('internal:role=link[name="Acme"s]')
        ->click('internal:role=button[name="More for Acme"s]')
        ->click('internal:role=menuitem[name="Rename…"s]')
        ->assertValue('#client-name', 'Acme')
        ->fill('#client-name', 'Acme Group')
        ->click('internal:role=dialog >> internal:role=button[name="Save"s]')
        ->assertSee('Client renamed.')
        ->hover('internal:role=link[name="Acme Group"s]')
        ->click('internal:role=button[name="More for Acme Group"s]')
        ->click('internal:role=menuitem[name="Delete…"s]')
        ->assertSee('Delete Acme Group')
        ->assertSee('Acme Group still has 1 Project. Move or delete them before deleting the Client.')
        ->click('internal:role=dialog >> internal:role=button[name="Delete Client"s]')
        ->assertSee('Acme Group still has Projects. Move or delete them before deleting the Client.')
        ->assertNoJavaScriptErrors();

    expect($acme->refresh()->name)->toBe('Acme Group')
        ->and(Client::query()->where('name', 'Globex')->exists())->toBeTrue();
});

it('adds and renames a Client on a phone', function () {
    Client::factory()->create(['name' => 'Acme']);

    actingAs(employeeWith([Permission::ManageClients->value => null]));

    visit(route('clients.index'))
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=button[name="Add Client…"s]')
        ->fill('#client-name', 'Globex')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="Add Client"s]')
        ->assertSee('Client created.')
        ->click('internal:role=button[name="More for Acme"s]')
        ->click('internal:role=menuitem[name="Rename…"s]')
        ->fill('#client-name', 'Acme Group')
        ->click('internal:role=dialog >> internal:role=button[name="Save"s]')
        ->assertSee('Client renamed.')
        ->assertSee('Acme Group')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect(Client::query()->orderBy('name')->pluck('name')->all())->toBe(['Acme Group', 'Globex']);
});

it('lists a Client\'s Assignments in one table grouped by Project, a Project no one is assigned to saying so', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Project::factory()->for($globex)->create(['name' => 'Relaunch', 'start_date' => today()->subYear()]);
    Project::factory()->for($globex)->create(['name' => 'Audit', 'start_date' => today()->subYear()]);
    assignmentOf(Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']), attributes: ['start_date' => today()->subMonth(), 'capacity' => 60])
        ->project()->associate($relaunch)->save();

    actingAs(employeeWith([Permission::ManageClients->value => null, Permission::ViewProjects->value => null, Permission::ViewAssignments->value => Scope::Everyone]));

    visit(route('clients.show', $globex))
        ->assertCount('[data-slot="group-row"]', 2)
        ->assertSeeIn('[data-slot="group-row"]:has-text("Audit")', 'No one you can see is assigned today')
        ->assertSee('Cara Lane')
        ->assertSee('60%')
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

it('renames a Client from its page, staying there, and offers Delete from its ⋯ menu', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    Project::factory()->for($globex)->create(['name' => 'Relaunch']);

    actingAs(employeeWith([Permission::ManageClients->value => null, Permission::ViewProjects->value => null]));

    visit(route('clients.show', $globex))
        ->click('internal:role=button[name="Rename…"s]')
        ->assertValue('#client-name', 'Globex')
        ->fill('#client-name', 'Globex Corp')
        ->click('internal:role=dialog >> internal:role=button[name="Save"s]')
        ->assertSee('Client renamed.')
        ->assertPathIs('/clients/'.$globex->id)
        ->assertSee('Globex Corp')
        ->click('internal:role=button[name="More for this Client"s]')
        ->click('internal:role=menuitem[name="Delete Client…"s]')
        ->assertSee('Globex Corp still has 1 Project. Move or delete them before deleting the Client.')
        ->assertNoJavaScriptErrors();

    expect($globex->refresh()->name)->toBe('Globex Corp');
});

it('searches the Clients by name from the filter bar', function () {
    Client::factory()->create(['name' => 'Acme']);
    Client::factory()->create(['name' => 'Globex']);

    actingAs(employeeWith([Permission::ManageClients->value => null]));

    visit(route('clients.index'))
        ->assertSee('Acme')
        ->fill('[aria-label="Search by name"]', 'glob')
        ->assertQueryStringHas('search', 'glob')
        ->assertSee('Globex')
        ->assertDontSee('Acme')
        ->assertNoJavaScriptErrors();
});
