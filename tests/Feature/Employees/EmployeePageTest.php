<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EmployeeController;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Support\ReportingChain;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class, EmployeePolicy::class, DirectoryController::class, ReportingChain::class);

it('shows an Employee their own record, with no Assignments or Pay when they may only see the record', function () {
    $ben = Employee::factory()->top()->create(['name' => 'Ben Ford']);
    $finance = Department::factory()->create(['name' => 'Finance']);
    $ana = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Own], [
        'name' => 'Ana Pop',
        'email' => 'ana@acme.test',
        'department_id' => $finance->id,
        'manager_id' => $ben->id,
        'start_date' => '2025-01-06',
    ]);
    Assignment::factory()->internal()->for($ana)->create(['job_title' => 'Accountant']);

    actingAs($ana)->get(route('employees.show', $ana))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employees/Show')
            ->where('employee', ['id' => $ana->id, 'name' => 'Ana Pop', 'job_title' => 'Accountant'])
            ->where('record', [
                'email' => 'ana@acme.test',
                'status' => 'current',
                'start_date' => '2025-01-06',
                'leaving_date' => null,
                'department' => 'Finance',
                'manager' => ['id' => $ben->id, 'name' => 'Ben Ford'],
                'is_top' => false,
            ])
            ->where('assignments', null)
            ->where('canEdit', false),
        );
});

it('opens an Employee\'s page to a viewer who may only see their Assignments, without their record', function () {
    $eve = Employee::factory()->create();
    $assignment = Assignment::factory()->internal()->for($eve)->create(['job_title' => 'Designer']);

    actingAs(employeeWith([Permission::ViewAssignments->value => Scope::Everyone]))->get(route('employees.show', $eve))
        ->assertInertia(fn (Assert $page) => $page
            ->where('record', null)
            ->where('assignments.0.id', $assignment->id)
            ->where('canViewPay', false),
        );
});

it('refuses an Employee\'s page to a viewer who may see none of their record, Assignments or Pay', function () {
    $eve = Employee::factory()->create();

    actingAs(employeeWith([Permission::ViewDirectory->value => null, Permission::ViewEmployeeRecords->value => Scope::Own, Permission::ViewAssignments->value => Scope::Own, Permission::ViewPay->value => Scope::Own]))
        ->get(route('employees.show', $eve))
        ->assertForbidden();
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lets Scope decide whose record an Employee may see', function (Scope $scope, array $visible) {
    $dana = Employee::factory()->top()->create();
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => $scope], ['manager_id' => $dana->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    $people = [
        'Dana' => $dana,
        'Ben' => $ben,
        'Cara' => $cara,
        'Dev' => Employee::factory()->create(['manager_id' => $cara->id]),
        'Eve' => Employee::factory()->create(['manager_id' => $dana->id]),
    ];

    foreach ($people as $name => $employee) {
        actingAs($ben)->get(route('employees.show', $employee))
            ->assertStatus(in_array($name, $visible, true) ? 200 : 403);
    }
})->with([
    'own' => [Scope::Own, ['Ben']],
    'reports' => [Scope::Reports, ['Cara', 'Dev']],
    'everyone' => [Scope::Everyone, ['Dana', 'Ben', 'Cara', 'Dev', 'Eve']],
]);

it('shows a Manager their Report\'s record and Assignments but no Pay', function () {
    $ben = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Reports,
        Permission::ViewAssignments->value => Scope::Reports,
        Permission::ManageAssignments->value => Scope::Reports,
        Permission::ViewProjects->value => null,
    ], ['manager_id' => Employee::factory()->top()->create()->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    Assignment::factory()->internal()->for($cara)->create();

    actingAs($ben)->get(route('employees.show', $cara))
        ->assertInertia(fn (Assert $page) => $page
            ->where('record.manager.id', $ben->id)
            ->has('assignments', 1)
            ->missing('assignments.0.pay')
            ->where('canViewPay', false)
            ->missing('currency'),
        );
});

it('offers Edit only to those who manage Employees', function (array $grants, bool $expected) {
    $cara = Employee::factory()->create();

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, ...$grants]))->get(route('employees.show', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', $expected));
})->with([
    'manage employees' => [[Permission::ManageEmployees->value => null], true],
    'view only' => [[], false],
]);

it('lists, on a Former Employee\'s page, the Employees who still report to them and need a new Manager', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->former()->create(['manager_id' => $dana->id]);
    $cara = Employee::factory()->create(['name' => 'Cara Lane', 'manager_id' => $ben->id]);
    Employee::factory()->former()->create(['manager_id' => $ben->id]);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))->get(route('employees.show', $ben))
        ->assertInertia(fn (Assert $page) => $page
            ->where('record.status', 'former')
            ->where('record.manager.id', $dana->id)
            ->where('reportsNeedingAManager', [['id' => $cara->id, 'name' => 'Cara Lane']]),
        );
});

it('links to an Employee\'s page from their Reporting Chain only when the viewer may open it', function (array $grants, string $expected) {
    $ben = employeeWith([Permission::ViewDirectory->value => null, ...$grants], ['manager_id' => Employee::factory()->top()->create()->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs($ben)->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('reportingChain.employee.link', $expected));
})->with([
    'records of reports' => [[Permission::ViewEmployeeRecords->value => Scope::Reports], 'employee'],
    'assignments of reports' => [[Permission::ViewAssignments->value => Scope::Reports], 'employee'],
    'own only' => [[Permission::ViewEmployeeRecords->value => Scope::Own, Permission::ViewAssignments->value => Scope::Own], 'directory'],
]);

it('shows an Employee\'s Reporting Chain on their page to anyone who may open it, from the top down to those they are the Manager of', function () {
    $top = Employee::factory()->top()->create(['name' => 'Ada', 'department_id' => Department::factory()->create(['name' => 'Board'])->id]);
    $ana = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Own], ['name' => 'Ana', 'manager_id' => $top->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ana->id, 'department_id' => Department::factory()->create(['name' => 'Design'])->id]);

    $response = actingAs($ana)->get(route('employees.show', $ana));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('reportingChain.above', [
            ['id' => $top->id, 'name' => 'Ada', 'job_title' => null, 'department' => 'Board', 'status' => 'current', 'link' => null],
        ])
        ->where('reportingChain.employee.id', $ana->id)
        ->where('reportingChain.employee.link', 'employee')
        ->where('reportingChain.below', [
            ['id' => $cara->id, 'name' => 'Cara', 'job_title' => null, 'department' => 'Design', 'status' => 'current', 'link' => null],
        ]));
});

it('shows no Reporting Chain on a Former Employee\'s page', function () {
    $top = Employee::factory()->top()->create();
    $gone = Employee::factory()->former()->create(['manager_id' => $top->id]);

    $response = actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))->get(route('employees.show', $gone));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('reportingChain', null));
});
