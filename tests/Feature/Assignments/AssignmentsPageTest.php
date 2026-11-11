<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\DirectoryController;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Policies\AssignmentPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(AssignmentController::class, AssignmentPolicy::class, DirectoryController::class);

it('shows an Employee their own Assignments, earliest first, with nothing to manage', function () {
    $ana = employeeWith([Permission::ViewAssignments->value => Scope::Own], ['name' => 'Ana Pop', 'start_date' => '2025-01-06']);
    $globex = Client::factory()->create(['name' => 'Globex']);
    $relaunch = Project::factory()->forClient($globex)->create(['name' => 'Relaunch', 'start_date' => '2025-01-06']);
    $audit = Project::factory()->create(['name' => 'Audit', 'start_date' => '2025-01-06']);
    $later = Assignment::factory()->for($ana)->for($relaunch)->create(['job_title' => 'Tech Lead', 'start_date' => '2026-03-01', 'end_date' => null, 'share' => 40]);
    $earlier = Assignment::factory()->for($ana)->for($audit)->create(['job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'share' => 100]);

    actingAs($ana)->get(route('employees.assignments.index', $ana))
        ->assertInertia(fn (Assert $page) => $page
            ->component('assignments/Index')
            ->where('employee', ['id' => $ana->id, 'name' => 'Ana Pop'])
            ->where('assignments', [
                ['id' => $earlier->id, 'project' => ['id' => $audit->id, 'name' => 'Audit', 'client' => null], 'job_title' => 'Developer', 'start_date' => '2025-02-01', 'end_date' => '2025-12-31', 'share' => 100],
                ['id' => $later->id, 'project' => ['id' => $relaunch->id, 'name' => 'Relaunch', 'client' => 'Globex'], 'job_title' => 'Tech Lead', 'start_date' => '2026-03-01', 'end_date' => null, 'share' => 40],
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
        ->get(route('employees.assignments.index', $ana))
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
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    $people = [
        'Dana' => $dana,
        'Ben' => $ben,
        'Cara' => $cara,
        'Dev' => Employee::factory()->create(['manager_id' => $cara->id]),
        'Eve' => Employee::factory()->create(['manager_id' => $dana->id]),
    ];

    foreach ($people as $name => $employee) {
        actingAs($ben)->get(route('employees.assignments.index', $employee))
            ->assertStatus(in_array($name, $visible, true) ? 200 : 403);
    }
})->with([
    'own' => [Scope::Own, ['Ben']],
    'reports' => [Scope::Reports, ['Cara', 'Dev']],
    'everyone' => [Scope::Everyone, ['Dana', 'Ben', 'Cara', 'Dev', 'Eve']],
]);

it('lets an Employee who manages someone\'s Assignments see them too', function () {
    $ben = employeeWith([Permission::ManageAssignments->value => Scope::Reports], ['manager_id' => Employee::factory()->top()->create()->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs($ben)->get(route('employees.assignments.index', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('canManage', true));
});

it('shows Assignments read-only to an Employee who may see but not manage them', function () {
    $ben = employeeWith([Permission::ViewAssignments->value => Scope::Everyone, Permission::ManageAssignments->value => Scope::Reports], ['manager_id' => Employee::factory()->top()->create()->id]);
    $eve = Employee::factory()->create();

    actingAs($ben)->get(route('employees.assignments.index', $eve))
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('projects', []));
});

it('lets Scope decide whose Assignments an Employee may create and change', function (Scope $scope, array $allowed) {
    $dana = Employee::factory()->top()->create(['start_date' => '2024-01-01']);
    $ben = employeeWith([Permission::ManageAssignments->value => $scope], ['manager_id' => $dana->id, 'start_date' => '2024-01-01']);
    $cara = Employee::factory()->create(['manager_id' => $ben->id, 'start_date' => '2024-01-01']);
    $people = [
        'Dana' => $dana,
        'Ben' => $ben,
        'Cara' => $cara,
        'Dev' => Employee::factory()->create(['manager_id' => $cara->id, 'start_date' => '2024-01-01']),
        'Eve' => Employee::factory()->create(['manager_id' => $dana->id, 'start_date' => '2024-01-01']),
    ];
    $project = Project::factory()->create(['start_date' => '2024-01-01']);
    $form = ['project_id' => $project->id, 'job_title' => 'Analyst', 'start_date' => '2026-03-01', 'end_date' => null, 'share' => 50];

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
    get(route('employees.assignments.index', Employee::factory()->create()))
        ->assertRedirect(route('login'));
});

it('tells the pages whether the signed-in Employee may see their own Assignments', function (array $grants, bool $expected) {
    actingAs(employeeWith($grants))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeOwnAssignments', $expected));
})->with([
    'view own' => [[Permission::ViewAssignments->value => Scope::Own], true],
    'manage everyone' => [[Permission::ManageAssignments->value => Scope::Everyone], true],
    'reports only' => [[Permission::ViewAssignments->value => Scope::Reports], false],
    'neither' => [[Permission::ViewDirectory->value => null], false],
]);

it('offers a link to an Employee\'s Assignments from their directory page only when the viewer may see them', function (Scope $scope, bool $expected) {
    $ben = employeeWith([Permission::ViewDirectory->value => null, Permission::ViewAssignments->value => $scope], ['manager_id' => Employee::factory()->top()->create()->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs($ben)->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('canViewAssignments', $expected));
})->with([
    'own' => [Scope::Own, false],
    'reports' => [Scope::Reports, true],
]);
