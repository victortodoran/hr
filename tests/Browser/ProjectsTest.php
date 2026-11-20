<?php

use App\Enums\Permission;
use App\Http\Controllers\Projects\ProjectController;
use App\Models\Client;
use App\Models\Project;

use function Pest\Laravel\actingAs;

covers(ProjectController::class);

it('adds a Project in the New Project sheet, then edits it in the Edit Project sheet on its page', function () {
    Client::factory()->create(['name' => 'Globex']);
    Project::factory()->internal()->create(['name' => 'Acme Software']);

    actingAs(employeeWith([Permission::ManageProjects->value => null]));

    $page = visit(route('projects.index'))
        ->assertSee('Internal Project')
        ->click('internal:role=link[name="New Project"s]')
        ->assertPathIs('/projects/create')
        ->assertSee('Employees can be assigned to it while it runs.')
        ->fill('#project-name', 'Website relaunch')
        ->click('#project-client')
        ->click('internal:role=option[name="Globex"s]');

    pickDate($page, '#project-start', '2026-01-05')
        ->click('internal:role=dialog >> internal:role=button[name="New Project"s]')
        ->assertSee('Project created.')
        ->assertSee('Globex · Jan 5, 2026 – no end date')
        ->click('internal:role=button[name="Edit"s]')
        ->assertValue('#project-name', 'Website relaunch')
        ->fill('#project-name', 'Rebrand')
        ->click('internal:role=dialog >> internal:role=button[name="Save Project"s]')
        ->assertSee('Project saved.')
        ->assertSee('Rebrand')
        ->assertMissing('#project-name')
        ->assertNoJavaScriptErrors();

    expect(Project::query()->where('name', 'Rebrand')->exists())->toBeTrue();
});

it('closes the New Project sheet back to the Projects, and deletes a Project from its ⋯ menu', function () {
    Project::factory()->create(['name' => 'Audit']);

    actingAs(employeeWith([Permission::ManageProjects->value => null]));

    visit(route('projects.create'))
        ->assertSee('Employees can be assigned to it while it runs.')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->assertPathIs('/projects')
        ->hover('internal:role=link[name="Audit"s]')
        ->click('internal:role=button[name="More for Audit"s]')
        ->click('internal:role=menuitem[name="Delete…"s]')
        ->assertSee('Delete Audit')
        ->click('internal:role=dialog >> internal:role=button[name="Delete Project"s]')
        ->assertSee('Project deleted.')
        ->assertNoJavaScriptErrors();

    expect(Project::query()->count())->toBe(0);
});

it('adds a Project on a phone from the New Project sheet', function () {
    actingAs(employeeWith([Permission::ManageProjects->value => null]));

    $page = visit(route('projects.index'))
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('header >> internal:role=link[name="New Project"s]')
        ->assertPathIs('/projects/create')
        ->fill('#project-name', 'Audit');

    pickDate($page, '#project-start', '2026-03-02')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="New Project"s]')
        ->assertSee('Project created.')
        ->assertSee('Mar 2, 2026 – no end date')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect(Project::query()->sole()->name)->toBe('Audit');
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
