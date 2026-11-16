<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EmployeeController;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\AssignmentPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, EmployeeController::class, AssignmentPolicy::class, DirectoryController::class);

it('shows an Employee their own Assignments, the Internal Assignment among them, earliest first, saying which have ended, with nothing to manage', function () {
    travelTo('2026-03-01');
    $ana = employeeWith([Permission::ViewAssignments->value => Scope::Own], ['name' => 'Ana Pop', 'start_date' => '2025-01-06']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Project::factory()->forClient($globex)->create(['name' => 'Relaunch', 'start_date' => '2025-01-06']);
    $audit = Project::factory()->create(['name' => 'Audit', 'start_date' => '2025-01-06']);
    $internal = Assignment::factory()->internal()->for($ana)->create(['job_title' => 'Developer']);
    $later = Assignment::factory()->for($ana)->for($relaunch)->create(['job_title' => 'Tech Lead', 'start_date' => '2026-03-01', 'end_date' => null, 'capacity' => 40]);
    $earlier = Assignment::factory()->for($ana)->for($audit)->create(['job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'capacity' => 100]);

    actingAs($ana)->get(route('employees.show', ['employee' => $ana, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/Show')
            ->where('employee', ['id' => $ana->id, 'name' => 'Ana Pop', 'job_title' => 'Developer'])
            ->where('assignments', [
                ['id' => $internal->id, 'project' => ['id' => $internal->project_id, 'name' => $internal->project->name, 'client' => null, 'is_internal' => true], 'job_title' => 'Developer', 'start_date' => '2025-01-06', 'end_date' => null, 'capacity' => 0, 'has_ended' => false],
                ['id' => $earlier->id, 'project' => ['id' => $audit->id, 'name' => 'Audit', 'client' => null, 'is_internal' => false], 'job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'capacity' => 100, 'has_ended' => true],
                ['id' => $later->id, 'project' => ['id' => $relaunch->id, 'name' => 'Relaunch', 'client' => 'Globex', 'is_internal' => false], 'job_title' => 'Tech Lead', 'start_date' => '2026-03-01', 'end_date' => null, 'capacity' => 40, 'has_ended' => false],
            ])
            ->where('canManage', false)
            ->where('projects', []),
        );
});

it('offers an assignment manager the Projects still open to Assignments, by name', function () {
    travelTo('2026-02-10');
    $ana = Employee::factory()->create();
    $globex = Client::factory()->create(['name' => 'Globex']);
    $website = Project::factory()->forClient($globex)->create(['name' => 'Website', 'start_date' => '2026-01-05', 'end_date' => '2026-02-10']);
    $audit = Project::factory()->create(['name' => 'Audit', 'start_date' => '2026-04-01']);
    Project::factory()->create(['name' => 'Finished', 'start_date' => '2025-01-05', 'end_date' => '2026-02-09']);
    Project::factory()->internal()->create();

    actingAs(employeeWith([Permission::ManageAssignments->value => Scope::Everyone]))
        ->get(route('employees.show', ['employee' => $ana, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments', [])
            ->where('canManage', true)
            ->where('projects', [
                ['id' => $audit->id, 'name' => 'Audit', 'client' => null, 'start_date' => '2026-04-01', 'end_date' => null],
                ['id' => $website->id, 'name' => 'Website', 'client' => 'Globex', 'start_date' => '2026-01-05', 'end_date' => '2026-02-10'],
            ]),
        );
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lets Scope decide whose Assignments an Employee may see', function (Scope $scope, array $visible) {
    $dana = Employee::factory()->top()->create();
    $ben = employeeWith([Permission::ViewAssignments->value => $scope], ['manager_id' => $dana->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();
    $people = [
        'Dana' => $dana,
        'Ben' => $ben,
        'Cara' => $cara,
        'Dev' => Employee::factory()->reportingTo($cara)->create(),
        'Eve' => Employee::factory()->reportingTo($dana)->create(),
    ];

    foreach ($people as $name => $employee) {
        actingAs($ben)->get(route('employees.show', ['employee' => $employee, 'tab' => 'assignments']))
            ->assertStatus(in_array($name, $visible, true) ? 200 : 403);
    }
})->with([
    'own' => [Scope::Own, ['Ben']],
    'reports' => [Scope::Reports, ['Cara', 'Dev']],
    'everyone' => [Scope::Everyone, ['Dana', 'Ben', 'Cara', 'Dev', 'Eve']],
]);

it('lets an Employee who manages someone\'s Assignments see them too', function () {
    $ben = employeeWith([Permission::ManageAssignments->value => Scope::Reports], ['manager_id' => Employee::factory()->top()->create()->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('employees.show', ['employee' => $cara, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
});

it('shows Assignments read-only to an Employee who may see but not manage them', function () {
    $ben = employeeWith([Permission::ViewAssignments->value => Scope::Everyone, Permission::ManageAssignments->value => Scope::Reports], ['manager_id' => Employee::factory()->top()->create()->id]);
    $eve = Employee::factory()->create();

    actingAs($ben)->get(route('employees.show', ['employee' => $eve, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('projects', []));
});

it('lets Scope decide whose Assignments an Employee may create and change', function (Scope $scope, array $allowed) {
    $dana = Employee::factory()->top()->create(['start_date' => '2024-01-01']);
    $ben = employeeWith([Permission::ManageAssignments->value => $scope], ['manager_id' => $dana->id, 'start_date' => '2024-01-01']);
    $cara = Employee::factory()->reportingTo($ben)->create(['start_date' => '2024-01-01']);
    $people = [
        'Dana' => $dana,
        'Ben' => $ben,
        'Cara' => $cara,
        'Dev' => Employee::factory()->reportingTo($cara)->create(['start_date' => '2024-01-01']),
        'Eve' => Employee::factory()->reportingTo($dana)->create(['start_date' => '2024-01-01']),
    ];
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    $form = ['project_id' => $project->id, 'job_title' => 'Analyst', 'start_date' => '2026-03-01', 'end_date' => null, 'capacity' => 50];

    foreach ($people as $name => $employee) {
        $expected = in_array($name, $allowed, true) ? 302 : 403;
        $existing = Assignment::factory()->for($employee)->for($project)->create(['job_title' => 'Developer']);

        actingAs($ben)->post(route('employees.assignments.store', $employee), $form)->assertStatus($expected);
        actingAs($ben)->patch(route('assignments.update', $existing), $form)->assertStatus($expected);

        expect($employee->assignments()->where('job_title', 'Analyst')->count())->toBe($expected === 302 ? 2 : 0);
    }
})->with([
    'own' => [Scope::Own, ['Ben']],
    'reports' => [Scope::Reports, ['Cara', 'Dev']],
    'everyone' => [Scope::Everyone, ['Dana', 'Ben', 'Cara', 'Dev', 'Eve']],
]);

it('refuses to let an Employee who can only see Assignments change them', function () {
    $ana = employeeWith([Permission::ViewAssignments->value => Scope::Everyone]);
    $assignment = Assignment::factory()->create(['job_title' => 'Developer']);

    actingAs($ana)->post(route('employees.assignments.store', $assignment->employee), ['job_title' => 'Analyst'])->assertForbidden();
    actingAs($ana)->patch(route('assignments.update', $assignment), ['job_title' => 'Analyst'])->assertForbidden();

    expect(Assignment::query()->sole()->job_title)->toBe('Developer');
});

it('sends a guest to sign in instead of showing Assignments', function () {
    get(route('employees.show', Employee::factory()->create()))
        ->assertRedirect(route('login'));
});
