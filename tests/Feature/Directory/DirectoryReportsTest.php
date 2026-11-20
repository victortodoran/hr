<?php

use App\Enums\DirectoryTab;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\DirectoryController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\Employees\DirectoryListRequest;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(DirectoryController::class, DirectoryListRequest::class, DirectoryTab::class, Employee::class, EmployeePolicy::class, HandleInertiaRequests::class);

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

    actingAs($ben)->get(route('directory.index', ['tab' => 'reports']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('directory/Index')
            ->where('tab', 'reports')
            ->where('tabs', ['reports'])
            ->missing('employees')
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

    actingAs($ana)->get(route('directory.index', ['tab' => 'reports']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('directReports.0.id', $cara->id)
            ->where('directReports.1.id', $dev->id)
            ->has('directReports', 2),
        );
});

it('links a Direct Report to their Employee page only when the Manager may open it', function () {
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Reports], ['name' => 'Ben Manager']);
    Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Report']);

    actingAs($ben)->get(route('directory.index', ['tab' => 'reports']))
        ->assertInertia(fn (Assert $page) => $page->where('directReports.0.can_view', true));
});

it('refuses the Directory to an Employee with no Direct Reports, Former ones included, and no view directory', function () {
    $ben = Employee::factory()->create();
    Employee::factory()->former()->reportingTo($ben)->create();

    actingAs($ben)->get(route('directory.index', ['tab' => 'reports']))->assertForbidden();
});

it('tells every page whether the viewer has Direct Reports, so the menu shows Directory to them', function () {
    $ben = Employee::factory()->create();
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTeam', true)->where('auth.canSeeDirectory', true));
    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTeam', false)->where('auth.canSeeDirectory', false));
});

it('shows both tabs to a Manager with view directory, opening the one asked for', function () {
    $ben = employeeWith([Permission::ViewDirectory->value => null]);
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('directory.index', ['tab' => 'reports']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'reports')
            ->where('tabs', ['everyone', 'reports'])
            ->where('directReports.0.id', $cara->id)
            ->missing('employees'),
        );
    actingAs($ben)->get(route('directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'everyone')
            ->has('employees.data')
            ->missing('directReports'),
        );
});

it('opens the first tab the viewer may open when asked for one they may not, or one that does not exist', function (string $asked) {
    $viewer = employeeWith([Permission::ViewDirectory->value => null]);

    actingAs($viewer)->get(route('directory.index', ['tab' => $asked]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'everyone')->where('tabs', ['everyone']));
})->with(['reports', 'nonsense']);
