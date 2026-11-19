<?php

use App\Enums\Permission;
use App\Http\Controllers\Projects\ProjectController;
use App\Models\Client;
use App\Models\Project;

use function Pest\Laravel\actingAs;

covers(ProjectController::class);

it('creates a Project on its own page, then changes it in place on the Project\'s page', function () {
    Client::factory()->create(['name' => 'Globex']);
    Project::factory()->create(['name' => 'Audit']);

    actingAs(employeeWith([Permission::ManageProjects->value => null]));

    visit(route('projects.index'))
        ->click('internal:role=link[name="New Project"s]')
        ->assertSee('Employees can be assigned to it while it runs.')
        ->fill('#project-name', 'Website relaunch')
        ->click('#project-client')
        ->click('internal:role=option[name="Globex"s]')
        ->fill('#project-start', '2026-01-05')
        ->click('Create Project')
        ->assertSee('Project created.')
        ->assertSee('Globex · ')
        ->click('Change')
        ->assertValue('#project-name', 'Website relaunch')
        ->fill('#project-name', 'Rebrand')
        ->click('Save Project')
        ->assertSee('Project saved.')
        ->assertSee('Rebrand')
        ->assertMissing('#project-name')
        ->assertNoJavaScriptErrors();

    expect(Project::query()->where('name', 'Rebrand')->exists())->toBeTrue();
});

it('searches the Projects by name from the filter bar, back on the first page', function () {
    Project::factory()->internal()->create(['name' => 'Acme Software']);
    Project::factory()->create(['name' => 'Website relaunch']);
    Project::factory()->count(25)->create();

    actingAs(employeeWith([Permission::ViewProjects->value => null]));

    visit(route('projects.index', ['page' => 2]))
        ->assertSee('26–27 of 27')
        ->fill('[aria-label="Search by name"]', 'relau')
        ->assertQueryStringHas('search', 'relau')
        ->assertQueryStringMissing('page')
        ->assertSee('Website relaunch')
        ->assertDontSee('Acme Software')
        ->fill('[aria-label="Search by name"]', 'nothing like it')
        ->assertSee('None match “nothing like it”.')
        ->assertNoJavaScriptErrors();
});
