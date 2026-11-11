<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(ProjectController::class, ClientController::class, ProjectPolicy::class, Assignment::class, Scope::class, Employee::class);

it('shows a Project with its team, by name', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Project::factory()->forClient($globex)->create(['name' => 'Relaunch', 'start_date' => '2025-01-06', 'end_date' => '2026-12-31']);
    $zoe = Employee::factory()->create(['name' => 'Zoe', 'start_date' => '2025-01-06']);
    $ana = Employee::factory()->create(['name' => 'Ana', 'start_date' => '2025-01-06']);
    $zoes = Assignment::factory()->for($zoe)->for($relaunch)->create(['job_title' => 'Designer', 'start_date' => '2025-03-01', 'end_date' => null, 'capacity' => 50]);
    $anasLater = Assignment::factory()->for($ana)->for($relaunch)->create(['job_title' => 'Tech Lead', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'capacity' => 100]);
    $anasEarlier = Assignment::factory()->for($ana)->for($relaunch)->create(['job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'capacity' => 80]);
    Assignment::factory()->create();

    actingAs(employeeWith([Permission::ViewProjects->value => null, Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('projects.show', $relaunch))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Show')
            ->where('project', ['id' => $relaunch->id, 'name' => 'Relaunch', 'client' => ['id' => $globex->id, 'name' => 'Globex'], 'start_date' => '2025-01-06', 'end_date' => '2026-12-31', 'is_internal' => false])
            ->where('team', [
                ['id' => $anasEarlier->id, 'employee' => ['id' => $ana->id, 'name' => 'Ana'], 'job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'capacity' => 80],
                ['id' => $anasLater->id, 'employee' => ['id' => $ana->id, 'name' => 'Ana'], 'job_title' => 'Tech Lead', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'capacity' => 100],
                ['id' => $zoes->id, 'employee' => ['id' => $zoe->id, 'name' => 'Zoe'], 'job_title' => 'Designer', 'start_date' => '2025-03-01', 'end_date' => null, 'capacity' => 50],
            ]),
        );
});

it('shows the Internal Project with no Client and no dates', function () {
    $internal = Project::factory()->internal()->create(['name' => 'Acme Software']);

    actingAs(employeeWith([Permission::ViewProjects->value => null]))
        ->get(route('projects.show', $internal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('project', ['id' => $internal->id, 'name' => 'Acme Software', 'client' => null, 'start_date' => null, 'end_date' => null, 'is_internal' => true])
            ->where('team', []),
        );
});

it('refuses a Project\'s page to an Employee who can neither view nor manage Projects', function () {
    actingAs(employeeWith([Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('projects.show', Project::factory()->create()))
        ->assertForbidden();
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('shows on a Project\'s team and a Client\'s page only the Assignments the viewer\'s Scope covers', function (array $grants, array $visible) {
    $globex = Client::factory()->create();
    $project = Project::factory()->forClient($globex)->create(['start_date' => '2024-01-01']);
    $dana = Employee::factory()->top()->create(['name' => 'Dana', 'start_date' => '2024-01-01']);
    $ben = employeeWith([Permission::ViewProjects->value => null, Permission::ManageClients->value => null, ...$grants], ['name' => 'Ben', 'manager_id' => $dana->id, 'start_date' => '2024-01-01']);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id, 'start_date' => '2024-01-01']);
    Employee::factory()->create(['name' => 'Dev', 'manager_id' => $cara->id, 'start_date' => '2024-01-01']);
    Employee::factory()->create(['name' => 'Eve', 'manager_id' => $dana->id, 'start_date' => '2024-01-01']);
    Employee::query()->each(fn (Employee $employee) => Assignment::factory()->for($employee)->for($project)->create());

    actingAs($ben)->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('team', fn (Collection $team) => $team->pluck('employee.name')->all() === $visible));
    actingAs($ben)->get(route('clients.show', $globex))
        ->assertInertia(fn (Assert $page) => $page->where('assignments', fn (Collection $assignments) => $assignments->pluck('employee.name')->all() === $visible));
})->with([
    'no Assignment Permission' => [[], []],
    'view, own' => [[Permission::ViewAssignments->value => Scope::Own], ['Ben']],
    'view, reports' => [[Permission::ViewAssignments->value => Scope::Reports], ['Cara', 'Dev']],
    'view, everyone' => [[Permission::ViewAssignments->value => Scope::Everyone], ['Ben', 'Cara', 'Dana', 'Dev', 'Eve']],
    'manage, reports' => [[Permission::ManageAssignments->value => Scope::Reports], ['Cara', 'Dev']],
    'view own, manage reports' => [[Permission::ViewAssignments->value => Scope::Own, Permission::ManageAssignments->value => Scope::Reports], ['Ben', 'Cara', 'Dev']],
    'view own, manage everyone' => [[Permission::ViewAssignments->value => Scope::Own, Permission::ManageAssignments->value => Scope::Everyone], ['Ben', 'Cara', 'Dana', 'Dev', 'Eve']],
]);

it('shows a Client with the Employees assigned to its Projects', function () {
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Project::factory()->forClient($globex)->create(['name' => 'Relaunch', 'start_date' => '2025-01-06']);
    $audit = Project::factory()->forClient($globex)->create(['name' => 'Audit', 'start_date' => '2025-01-06']);
    $ana = Employee::factory()->create(['name' => 'Ana', 'start_date' => '2025-01-06']);
    $zoe = Employee::factory()->create(['name' => 'Zoe', 'start_date' => '2025-01-06']);
    $zoes = Assignment::factory()->for($zoe)->for($audit)->create(['job_title' => 'Auditor', 'start_date' => '2025-02-01', 'end_date' => null, 'capacity' => 20]);
    $anasRelaunch = Assignment::factory()->for($ana)->for($relaunch)->create(['job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'capacity' => 80]);
    $anasAudit = Assignment::factory()->for($ana)->for($audit)->create(['job_title' => 'Analyst', 'start_date' => '2025-03-01', 'end_date' => null, 'capacity' => 20]);
    Assignment::factory()->for(Project::factory()->forClient())->create();

    actingAs(employeeWith([Permission::ManageClients->value => null, Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('clients.show', $globex))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('client', ['id' => $globex->id, 'name' => 'Globex'])
            ->where('assignments', [
                ['id' => $anasRelaunch->id, 'employee' => ['id' => $ana->id, 'name' => 'Ana'], 'project' => ['id' => $relaunch->id, 'name' => 'Relaunch'], 'job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'capacity' => 80],
                ['id' => $anasAudit->id, 'employee' => ['id' => $ana->id, 'name' => 'Ana'], 'project' => ['id' => $audit->id, 'name' => 'Audit'], 'job_title' => 'Analyst', 'start_date' => '2025-03-01', 'end_date' => null, 'capacity' => 20],
                ['id' => $zoes->id, 'employee' => ['id' => $zoe->id, 'name' => 'Zoe'], 'project' => ['id' => $audit->id, 'name' => 'Audit'], 'job_title' => 'Auditor', 'start_date' => '2025-02-01', 'end_date' => null, 'capacity' => 20],
            ]),
        );
});

it('refuses a Client\'s page to an Employee who doesn\'t manage Clients', function () {
    actingAs(employeeWith([Permission::ViewAssignments->value => Scope::Everyone, Permission::ViewProjects->value => null]))
        ->get(route('clients.show', Client::factory()->create()))
        ->assertForbidden();
});
