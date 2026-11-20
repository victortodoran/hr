<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use App\Rules\AssignmentsFit;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(ProjectController::class, ProjectRequest::class, ProjectPolicy::class, Project::class, AssignmentsFit::class);

/**
 * An Employee who may create, edit and delete Projects.
 */
function projectManager(): Employee
{
    return employeeWith([Permission::ManageProjects->value => null]);
}

/**
 * A valid Project form, with any fields overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function projectForm(array $overrides = []): array
{
    return [
        'name' => 'Website relaunch',
        'client_id' => null,
        'start_date' => '2026-01-05',
        'end_date' => null,
        ...$overrides,
    ];
}

it('lists the Projects with their Client and dates, the Internal Project first', function () {
    $internal = Project::factory()->internal()->create(['name' => 'Acme Software']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Project::factory()->forClient($globex)->create(['name' => 'Website relaunch', 'start_date' => '2026-01-05', 'end_date' => '2026-06-30']);
    $audit = Project::factory()->create(['name' => 'Audit', 'start_date' => '2026-03-01']);

    actingAs(employeeWith([Permission::ViewProjects->value => null]))->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('projects.data', [
                ['id' => $internal->id, 'name' => 'Acme Software', 'client' => null, 'start_date' => null, 'end_date' => null, 'is_internal' => true],
                ['id' => $audit->id, 'name' => 'Audit', 'client' => null, 'start_date' => '2026-03-01', 'end_date' => null, 'is_internal' => false],
                ['id' => $relaunch->id, 'name' => 'Website relaunch', 'client' => ['id' => $globex->id, 'name' => 'Globex'], 'start_date' => '2026-01-05', 'end_date' => '2026-06-30', 'is_internal' => false],
            ]),
        );
});

it('searches the Projects by any part of a name, ignoring case and treating % and _ as ordinary characters', function () {
    Project::factory()->internal()->create(['name' => 'Acme Software']);
    $relaunch = Project::factory()->create(['name' => 'Website Relaunch']);
    $odd = Project::factory()->create(['name' => 'Audit 100% done']);
    Project::factory()->create(['name' => 'Audit 1000 done']);
    $viewer = employeeWith([Permission::ViewProjects->value => null]);

    actingAs($viewer)->get(route('projects.index', ['search' => 'relau']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'relau')
            ->has('projects.data', 1)->where('projects.data.0.id', $relaunch->id),
        );

    actingAs($viewer)->get(route('projects.index', ['search' => '0%']))
        ->assertInertia(fn (Assert $page) => $page->has('projects.data', 1)->where('projects.data.0.id', $odd->id));
});

it('lists the Projects 25 at a time', function () {
    Project::factory()->internal()->create();
    Project::factory()->count(25)->create();

    actingAs(employeeWith([Permission::ViewProjects->value => null]))->get(route('projects.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.total', 26),
        );
});

it('rejects a Project search that is not text or is too long', function (mixed $search, string $message) {
    actingAs(employeeWith([Permission::ViewProjects->value => null]))
        ->get(route('projects.index', ['search' => $search]))
        ->assertSessionHasErrors(['search' => $message]);
})->with([
    'not text' => [['Acme'], 'The search field must be a string.'],
    'too long' => [str_repeat('a', 256), 'The search field must not be greater than 255 characters.'],
]);

it('opens the New Project sheet over the Projects, offering the Clients by name', function () {
    $internal = Project::factory()->internal()->create();
    $initech = Client::factory()->create(['name' => 'Initech']);
    $globex = Client::factory()->create(['name' => 'Globex']);

    actingAs(projectManager())->get(route('projects.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('creating', true)
            ->where('search', '')
            ->where('projects.data.0.id', $internal->id)
            ->where('clients', [['id' => $globex->id, 'name' => 'Globex'], ['id' => $initech->id, 'name' => 'Initech']]),
        );
});

it('keeps the Projects search behind the New Project sheet', function () {
    Project::factory()->internal()->create(['name' => 'Acme Software']);
    $audit = Project::factory()->create(['name' => 'Audit']);

    actingAs(projectManager())->get(route('projects.create', ['search' => 'aud']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('creating', true)
            ->where('search', 'aud')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $audit->id),
        );
});

it('refuses the New Project sheet to an Employee who can only view Projects', function () {
    actingAs(employeeWith([Permission::ViewProjects->value => null]))
        ->get(route('projects.create'))
        ->assertForbidden();
});

it('lets a Project manager change a Project on its page, offering the Clients', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    $project = Project::factory()->create();

    actingAs(projectManager())->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Show')
            ->where('canManage', true)
            ->where('clients', [['id' => $globex->id, 'name' => 'Globex']]),
        );
});

it('offers no changes on a Project\'s page to an Employee who can only view it, nor on the Internal Project\'s', function (Closure $viewer, Closure $project) {
    Client::factory()->create();

    actingAs($viewer())->get(route('projects.show', $project()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)
            ->where('clients', []),
        );
})->with([
    'viewer' => [fn (): Employee => employeeWith([Permission::ViewProjects->value => null]), fn (): Project => Project::factory()->create()],
    'Internal Project' => [fn (): Employee => projectManager(), fn (): Project => Project::factory()->internal()->create()],
]);

it('counts the Employees on a Project\'s team today and their Capacity in all, among those the viewer may see', function () {
    $project = Project::factory()->create(['start_date' => today()->subYear()]);
    $cara = Employee::factory()->startedLongAgo()->create();
    $dan = Employee::factory()->startedLongAgo()->create();
    Assignment::factory()->for($cara)->for($project)->create(['start_date' => today()->subMonth(), 'end_date' => today()->addMonth(), 'capacity' => 60]);
    Assignment::factory()->for($cara)->for($project)->create(['start_date' => today()->addMonths(2), 'end_date' => null, 'capacity' => 20]);
    Assignment::factory()->for($dan)->for($project)->create(['start_date' => today()->subMonth(), 'end_date' => null, 'capacity' => 40]);
    Assignment::factory()->for($dan)->for($project)->create(['start_date' => today()->subMonths(6), 'end_date' => today()->subMonths(2), 'capacity' => 100]);

    actingAs(employeeWith([Permission::ViewProjects->value => null, Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->count('team', 4)
            ->where('teamToday', ['employees' => 2, 'capacity' => 100]),
        );

    actingAs(employeeWith([Permission::ViewProjects->value => null]))
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('teamToday', ['employees' => 0, 'capacity' => 0]));
});

it('refuses the Projects page to an Employee who can neither view nor manage Projects', function () {
    actingAs(employeeWith([Permission::ManageClients->value => null]))
        ->get(route('projects.index'))
        ->assertForbidden();
});

it('lets a Project manager create a Project for a Client, with an end date', function () {
    $globex = Client::factory()->create();

    actingAs(projectManager())
        ->post(route('projects.store'), projectForm(['client_id' => $globex->id, 'end_date' => '2026-06-30']))
        ->assertRedirect(route('projects.show', Project::query()->sole()))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Project created.']);

    $project = Project::query()->sole();

    expect($project->name)->toBe('Website relaunch')
        ->and($project->client_id)->toBe($globex->id)
        ->and($project->start_date?->toDateString())->toBe('2026-01-05')
        ->and($project->end_date?->toDateString())->toBe('2026-06-30')
        ->and($project->is_internal)->toBeFalse();
});

it('lets a Project manager create a Project with no Client and no end date', function () {
    actingAs(projectManager())
        ->post(route('projects.store'), projectForm())
        ->assertSessionHasNoErrors();

    $project = Project::query()->sole();

    expect($project->client_id)->toBeNull()
        ->and($project->end_date)->toBeNull();
});

it('rejects an invalid Project and explains why', function (array $overrides, string $field, string $message) {
    actingAs(projectManager())
        ->post(route('projects.store'), projectForm($overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(Project::query()->count())->toBe(0);
})->with([
    'name missing' => [['name' => ''], 'name', 'The name field is required.'],
    'name not text' => [['name' => ['x']], 'name', 'The name field must be a string.'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name', 'The name field must not be greater than 255 characters.'],
    'start date missing' => [['start_date' => ''], 'start_date', 'The start date field is required.'],
    'start date not a date' => [['start_date' => 'soon'], 'start_date', 'The start date field must be a valid date.'],
    'end date not a date' => [['end_date' => 'later'], 'end_date', 'The end date field must be a valid date.'],
    'end date before start' => [['end_date' => '2026-01-04'], 'end_date', 'The end date field must be a date after or equal to start date.'],
    'Client not a number' => [['client_id' => 'globex'], 'client_id', 'The client field must be an integer.'],
    'unknown Client' => [['client_id' => 999999], 'client_id', 'The selected client is invalid.'],
]);

it('lets a Project end on the day it starts', function () {
    actingAs(projectManager())
        ->post(route('projects.store'), projectForm(['end_date' => '2026-01-05']))
        ->assertSessionHasNoErrors();
});

it('lets a Project manager change a Project\'s name, dates and Client', function () {
    $acme = Client::factory()->create();
    $project = Project::factory()->forClient($acme)->create();
    $globex = Client::factory()->create();

    actingAs(projectManager())
        ->patch(route('projects.update', $project), projectForm(['name' => 'Rebrand', 'client_id' => $globex->id, 'start_date' => '2026-02-01', 'end_date' => '2026-12-31']))
        ->assertRedirect(route('projects.show', $project))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Project saved.']);

    $project->refresh();

    expect($project->name)->toBe('Rebrand')
        ->and($project->client_id)->toBe($globex->id)
        ->and($project->start_date?->toDateString())->toBe('2026-02-01')
        ->and($project->end_date?->toDateString())->toBe('2026-12-31');
});

it('lets a Project manager take a Project off its Client and clear its end date', function () {
    $project = Project::factory()->forClient()->create(['end_date' => today()->addMonth()]);

    actingAs(projectManager())
        ->patch(route('projects.update', $project), projectForm())
        ->assertSessionHasNoErrors();

    expect($project->refresh()->client_id)->toBeNull()
        ->and($project->end_date)->toBeNull();
});

it('validates a Project being edited', function () {
    $project = Project::factory()->create(['name' => 'Audit']);

    actingAs(projectManager())
        ->patch(route('projects.update', $project), projectForm(['end_date' => '2025-12-31']))
        ->assertSessionHasErrors(['end_date' => 'The end date field must be a date after or equal to start date.']);

    expect($project->refresh()->name)->toBe('Audit');
});

it('lets a Project manager delete a Project', function () {
    $project = Project::factory()->create();

    actingAs(projectManager())
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Project deleted.']);

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
});

it('refuses to edit, end, attach to a Client or delete the Internal Project', function (string $method, Closure $url, Closure $form) {
    $internal = Project::factory()->internal()->create(['name' => 'Acme Software']);

    actingAs(employeeWith([Permission::ManageProjects->value => null, Permission::ManageCompany->value => null]))
        ->{$method}($url($internal), $form())
        ->assertForbidden();

    $internal->refresh();

    expect($internal->name)->toBe('Acme Software')
        ->and($internal->client_id)->toBeNull()
        ->and($internal->end_date)->toBeNull();
})->with([
    'end it' => ['patch', fn (Project $project): string => route('projects.update', $project), fn (): array => projectForm(['name' => 'Acme Software', 'end_date' => '2026-12-31'])],
    'attach a Client' => ['patch', fn (Project $project): string => route('projects.update', $project), fn (): array => projectForm(['name' => 'Acme Software', 'client_id' => Client::factory()->create()->id])],
    'rename it' => ['patch', fn (Project $project): string => route('projects.update', $project), fn (): array => projectForm(['name' => 'Something else'])],
    'delete it' => ['delete', fn (Project $project): string => route('projects.destroy', $project), fn (): array => []],
]);

it('refuses to change Projects to an Employee who can only view them', function (string $method, Closure $url) {
    $project = Project::factory()->create(['name' => 'Audit']);

    actingAs(employeeWith([Permission::ViewProjects->value => null]))
        ->{$method}($url($project), projectForm(['name' => 'Renamed']))
        ->assertForbidden();

    expect(Project::query()->count())->toBe(1)
        ->and($project->refresh()->name)->toBe('Audit');
})->with([
    'create' => ['post', fn (Project $project): string => route('projects.store')],
    'edit' => ['patch', fn (Project $project): string => route('projects.update', $project)],
    'delete' => ['delete', fn (Project $project): string => route('projects.destroy', $project)],
]);

it('lists on a Project\'s team whoever the viewer may see the Assignments of, including through seeing their Pay', function (string $permission, bool $listed) {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara)->project;
    $viewer = employeeWith([Permission::ViewProjects->value => null, $permission => Scope::Everyone]);

    expect($viewer->can('viewAny', [Assignment::class, $cara]))->toBe($listed);

    actingAs($viewer)->get(route('projects.show', $relaunch))
        ->assertInertia(fn (Assert $page) => $page->count('team', $listed ? 1 : 0));
})->with([
    'view assignments' => [Permission::ViewAssignments->value, true],
    'manage assignments' => [Permission::ManageAssignments->value, true],
    'view pay' => [Permission::ViewPay->value, true],
    'manage pay' => [Permission::ManagePay->value, true],
    'view time off' => [Permission::ViewTimeOff->value, false],
]);
