<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(TeamController::class, Employee::class, EmployeePolicy::class, HandleInertiaRequests::class);

it('shows a Manager their Direct Reports by name, with email, internal Job Title and Department', function () {
    $ben = Employee::factory()->create(['name' => 'Ben Manager']);
    $zoe = Employee::factory()->create([
        'name' => 'Zoe Report',
        'email' => 'zoe@acme.test',
        'manager_id' => $ben->id,
        'department_id' => Department::factory()->create(['name' => 'Design'])->id,
    ]);
    $cara = Employee::factory()->create([
        'name' => 'Cara Report',
        'email' => 'cara@acme.test',
        'manager_id' => $ben->id,
        'department_id' => Department::factory()->create(['name' => 'Engineering'])->id,
    ]);
    Assignment::factory()->internal()->for($cara)->create(['job_title' => 'Developer']);

    actingAs($ben)->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('team/Index')
            ->where('directReports', [
                ['id' => $cara->id, 'name' => 'Cara Report', 'email' => 'cara@acme.test', 'job_title' => 'Developer', 'department' => 'Engineering', 'can_view' => false],
                ['id' => $zoe->id, 'name' => 'Zoe Report', 'email' => 'zoe@acme.test', 'job_title' => null, 'department' => 'Design', 'can_view' => false],
            ]),
        );
});

/*
 * Ana (top)
 * ├── Ben (left) ── Cara
 * ├── Dev (future) ── Eve
 * └── Fay (former)
 */
it('lists the Employees of a Manager who has left and Future Direct Reports, but not Former ones or Reports further down', function () {
    $ana = Employee::factory()->create(['name' => 'Ana Top']);
    $ben = Employee::factory()->former()->reportingTo($ana)->create(['name' => 'Ben Left']);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Report']);
    $dev = Employee::factory()->future()->reportingTo($ana)->create(['name' => 'Dev Future']);
    Employee::factory()->reportingTo($dev)->create(['name' => 'Eve Below']);
    Employee::factory()->former()->reportingTo($ana)->create(['name' => 'Fay Former']);

    actingAs($ana)->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('directReports.0.id', $cara->id)
            ->where('directReports.1.id', $dev->id)
            ->has('directReports', 2),
        );
});

it('links a Direct Report to their Employee page only when the Manager may open it', function () {
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Reports], ['name' => 'Ben Manager']);
    Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Report']);

    actingAs($ben)->get(route('team.index'))
        ->assertInertia(fn (Assert $page) => $page->where('directReports.0.can_view', true));
});

it('refuses the Team page to an Employee with no Direct Reports, Former ones included', function () {
    $ben = Employee::factory()->create();
    Employee::factory()->former()->reportingTo($ben)->create();

    actingAs($ben)->get(route('team.index'))->assertForbidden();
});

it('tells every page whether the viewer has Direct Reports, so the menu shows Team only then', function () {
    $ben = Employee::factory()->create();
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTeam', true));
    actingAs($cara)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTeam', false));
});
