<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\DirectoryController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Pages\Employees\EmployeePage;
use App\Policies\EmployeePolicy;
use App\Support\ReportingChain;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeePolicy::class, DirectoryController::class, ReportingChain::class, EmployeePage::class);

it('shows an Employee their own record, with no Assignments or Pay when they may only see the record', function () {
    $ben = Employee::factory()->create(['name' => 'Ben Ford']);
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
                'start_date' => '2025-01-06',
                'leaving_date' => null,
                'department' => 'Finance',
                'manager' => ['id' => $ben->id, 'name' => 'Ben Ford'],
            ])
            ->where('tabs', ['overview'])
            ->where('canEdit', false),
        );
});

it('shows a Manager who also holds the Employee Role their own record, Assignments with Pay and Time Off on their Profile', function () {
    CompanySettings::factory()->create();
    $ben = employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Own,
        Permission::ViewAssignments->value => Scope::Own,
        Permission::ViewPay->value => Scope::Own,
        Permission::ViewTimeOff->value => Scope::Own,
    ]);
    giveRole($ben, [
        Permission::ViewEmployeeRecords->value => Scope::Reports,
        Permission::ViewAssignments->value => Scope::Reports,
        Permission::ViewPay->value => Scope::Reports,
        Permission::ViewTimeOff->value => Scope::Reports,
    ]);
    Employee::factory()->reportingTo($ben)->create();
    Assignment::factory()->internal()->for($ben)->create();

    actingAs($ben)->get(route('employees.show', $ben))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tabs', ['overview', 'assignments', 'time-off'])
            ->whereNot('record', null));
    get(route('employees.show', ['employee' => $ben, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->has('assignments.0.pay'));
    get(route('employees.show', ['employee' => $ben, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page->whereNot('timeOff', null));
});

it('opens an Employee\'s page to a viewer who may only see their Assignments, without their record', function () {
    $eve = Employee::factory()->create();
    $assignment = Assignment::factory()->internal()->for($eve)->create(['job_title' => 'Designer']);
    $viewer = employeeWith([Permission::ViewAssignments->value => Scope::Everyone]);

    actingAs($viewer)->get(route('employees.show', $eve))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tabs', ['overview', 'assignments'])
            ->where('record', null),
        );
    actingAs($viewer)->get(route('employees.show', ['employee' => $eve, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page
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

it('always opens their Profile to an Employee with no Roles, with their Reporting Chain but not their record, and still refuses them anyone else\'s page', function () {
    $top = Employee::factory()->create(['name' => 'Ada']);
    $ana = Employee::factory()->create(['name' => 'Ana', 'manager_id' => $top->id]);
    Assignment::factory()->internal()->for($ana)->create(['job_title' => 'Accountant']);
    $cara = Employee::factory()->reportingTo($ana)->create(['name' => 'Cara']);

    actingAs($ana)->get(route('employees.show', ['employee' => $ana, 'tab' => 'time-off']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee', ['id' => $ana->id, 'name' => 'Ana', 'job_title' => 'Accountant'])
            ->where('tabs', ['overview'])
            ->where('tab', 'overview')
            ->where('record', null)
            ->where('standing', null)
            ->where('reportingChain.above.0.name', 'Ada')
            ->where('reportingChain.employee.link', 'employee')
            ->where('reportingChain.below.0.name', 'Cara'),
        );
    actingAs($ana)->get(route('employees.show', $cara))->assertForbidden();
    actingAs($ana)->get(route('employees.show', $top))->assertForbidden();
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lets Scope decide whose record an Employee may see', function (Scope $scope, array $visible, bool $seesOwnRecord) {
    $dana = Employee::factory()->create();
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => $scope], ['manager_id' => $dana->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();
    $others = [
        'Dana' => $dana,
        'Cara' => $cara,
        'Dev' => Employee::factory()->reportingTo($cara)->create(),
        'Eve' => Employee::factory()->reportingTo($dana)->create(),
    ];

    foreach ($others as $name => $employee) {
        actingAs($ben)->get(route('employees.show', $employee))
            ->assertStatus(in_array($name, $visible, true) ? 200 : 403);
    }
    actingAs($ben)->get(route('employees.show', $ben))->assertOk()
        ->assertInertia(fn (Assert $page) => $seesOwnRecord ? $page->has('record.email') : $page->where('record', null));
})->with([
    'own' => [Scope::Own, [], true],
    'reports' => [Scope::Reports, ['Cara', 'Dev'], false],
]);

it('offers Edit only to those who manage Employees with a Scope that covers them', function (array $grants, bool $expected) {
    $cara = Employee::factory()->create();

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, ...$grants]))->get(route('employees.show', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', $expected));
})->with([
    'manage employees for everyone' => [[Permission::ManageEmployees->value => Scope::Everyone], true],
    'manage employees for their Reports only' => [[Permission::ManageEmployees->value => Scope::Reports], false],
    'view only' => [[], false],
]);

it('lists, on a Former Employee\'s page, the Employees who still report to them and need a new Manager', function () {
    $dana = Employee::factory()->create();
    $ben = Employee::factory()->former()->reportingTo($dana)->create();
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara Lane']);
    Employee::factory()->former()->reportingTo($ben)->create();

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))->get(route('employees.show', $ben))
        ->assertInertia(fn (Assert $page) => $page
            ->where('record.manager.id', $dana->id)
            ->where('reportsNeedingAManager', [['id' => $cara->id, 'name' => 'Cara Lane']]),
        );
});

it('gives an Employee\'s employment status on every tab to those who may see their record, and to nobody else', function () {
    $ana = Employee::factory()->create();
    $ben = Employee::factory()->former()->reportingTo($ana)->create();
    $recordViewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::ViewAssignments->value => Scope::Everyone, Permission::ViewTimeOff->value => Scope::Everyone]);
    $assignmentViewer = employeeWith([Permission::ViewAssignments->value => Scope::Everyone]);

    foreach (['overview', 'assignments', 'time-off'] as $tab) {
        actingAs($recordViewer)->get(route('employees.show', ['employee' => $ben, 'tab' => $tab]))
            ->assertInertia(fn (Assert $page) => $page->where('tab', $tab)->where('standing', ['status' => 'former']));
    }
    actingAs($recordViewer)->get(route('employees.show', $ana))
        ->assertInertia(fn (Assert $page) => $page->where('standing', ['status' => 'current']));
    actingAs($assignmentViewer)->get(route('employees.show', ['employee' => $ben, 'tab' => 'assignments']))
        ->assertInertia(fn (Assert $page) => $page->where('standing', null));
});

it('links to an Employee\'s page from their Reporting Chain only when the viewer may open it', function (array $grants, string $expected) {
    $ben = employeeWith([Permission::ViewDirectory->value => null, ...$grants], ['manager_id' => Employee::factory()->create()->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('directory.show', $cara))
        ->assertInertia(fn (Assert $page) => $page->where('reportingChain.employee.link', $expected));
})->with([
    'assignments of reports' => [[Permission::ViewAssignments->value => Scope::Reports], 'employee'],
    'own only' => [[Permission::ViewEmployeeRecords->value => Scope::Own, Permission::ViewAssignments->value => Scope::Own], 'directory'],
]);

it('shows an Employee\'s Reporting Chain on their page to anyone who may open it, from one with no Manager down to those they are the Manager of', function () {
    $top = Employee::factory()->create(['name' => 'Ada', 'department_id' => Department::factory()->create(['name' => 'Board'])->id]);
    $ana = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Own], ['name' => 'Ana', 'manager_id' => $top->id]);
    $cara = Employee::factory()->reportingTo($ana)->create(['name' => 'Cara', 'department_id' => Department::factory()->create(['name' => 'Design'])->id]);

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
    $top = Employee::factory()->create();
    $gone = Employee::factory()->former()->reportingTo($top)->create();

    $response = actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))->get(route('employees.show', $gone));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('reportingChain', null));
});

/*
 * Ben ── Cara
 */
it('offers each viewer only the tabs they may see, each carrying only its own data, and opens Overview for a tab they may not see', function (Closure $viewer, array $offered) {
    CompanySettings::factory()->create();
    $ben = Employee::factory()->create(['manager_id' => Employee::factory()->create()->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();
    $props = [
        'overview' => ['record', 'reportsNeedingAManager', 'reportingChain', 'seeAlso'],
        'assignments' => ['assignments', 'projects'],
        'time-off' => ['paidDays', 'timeOff'],
    ];
    actingAs($viewer($ben, $cara));

    foreach (array_keys($props) as $asked) {
        $opened = in_array($asked, $offered, true) ? $asked : 'overview';

        get(route('employees.show', ['employee' => $cara, 'tab' => $asked]))->assertOk()
            ->assertInertia(function (Assert $page) use ($props, $offered, $opened) {
                $page->where('tab', $opened)->where('tabs', $offered);
                foreach ($props as $tab => $keys) {
                    foreach ($keys as $key) {
                        $tab === $opened ? $page->has($key) : $page->missing($key);
                    }
                }
            });
    }
})->with([
    'themselves, seeing their own records, Assignments and Time Off' => [fn (Employee $ben, Employee $cara) => tap($cara, fn () => giveRole($cara, [
        Permission::ViewDirectory->value => null,
        Permission::ViewEmployeeRecords->value => Scope::Own,
        Permission::ViewAssignments->value => Scope::Own,
        Permission::ViewPay->value => Scope::Own,
        Permission::ViewTimeOff->value => Scope::Own,
    ])), ['overview', 'assignments', 'time-off']],
    'their Manager, seeing their Reports\' records, Assignments and Time Off' => [fn (Employee $ben) => tap($ben, fn () => giveRole($ben, [
        Permission::ViewEmployeeRecords->value => Scope::Reports,
        Permission::ViewAssignments->value => Scope::Reports,
        Permission::ManageAssignments->value => Scope::Reports,
        Permission::ViewTimeOff->value => Scope::Reports,
        Permission::ViewProjects->value => null,
    ])), ['overview', 'assignments', 'time-off']],
    'HR, with full access' => [fn () => employeeWith([
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
        Permission::ManageEmployees->value => Scope::Everyone,
        Permission::ViewAssignments->value => Scope::Everyone,
        Permission::ManageAssignments->value => Scope::Everyone,
        Permission::ViewPay->value => Scope::Everyone,
        Permission::ManagePay->value => Scope::Everyone,
        Permission::ManagePaidDays->value => Scope::Everyone,
        Permission::ViewProjects->value => null,
    ]), ['overview', 'assignments', 'time-off']],
    'an Employee who may only see records across the directory' => [fn () => employeeWith([
        Permission::ViewDirectory->value => null,
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
    ]), ['overview']],
]);

it('opens Overview when no tab, or an unknown one, is asked for', function (array $query) {
    $cara = Employee::factory()->create();

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::ViewAssignments->value => Scope::Everyone]))
        ->get(route('employees.show', ['employee' => $cara, ...$query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'overview')
            ->has('record')
            ->missing('assignments'));
})->with([
    'no tab' => [[]],
    'an unknown tab' => [['tab' => 'pay']],
    'a tab in the wrong case' => [['tab' => 'Assignments']],
    'a list instead of a tab' => [['tab' => ['assignments']]],
]);

/*
 * Ada ── Ben ── Cara
 */
it('links the Overview to the Work, Travel and Performance lists only where the viewer may see the Employee on them', function (Closure $viewer, string $whose, array $expected) {
    travelTo('2026-11-13 09:00');
    $ada = Employee::factory()->create(['name' => 'Ada']);
    $ben = Employee::factory()->reportingTo($ada)->create(['name' => 'Ben']);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara']);
    $actor = $viewer($ben);
    giveRole($actor, [Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $employee = ['cara' => $cara, 'ben' => $ben, 'themselves' => $actor][$whose];

    actingAs($actor)->get(route('employees.show', $employee))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seeAlso', $expected));
})->with([
    'one who sees everyone\'s Work Entries' => [fn () => employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]), 'cara', ['work' => true, 'travel' => false, 'performance' => null]],
    'one who sees everyone\'s travel' => [fn () => employeeWith([Permission::ViewTravel->value => Scope::Everyone]), 'cara', ['work' => false, 'travel' => true, 'performance' => null]],
    'one who reads everyone\'s Evaluations' => [fn () => employeeWith([Permission::ViewPerformanceEvaluations->value => Scope::Everyone]), 'cara', ['work' => false, 'travel' => false, 'performance' => 2026]],
    'her Manager, seeing his Reports\' Work Entries and travel' => [fn (Employee $ben) => tap($ben, fn () => giveRole($ben, [Permission::ViewWorkEntries->value => Scope::Reports, Permission::ViewTravel->value => Scope::Reports])), 'cara', ['work' => true, 'travel' => true, 'performance' => 2026]],
    'one whose Scope stops at their Reports, on someone else' => [fn () => employeeWith([Permission::ViewWorkEntries->value => Scope::Reports, Permission::ViewTravel->value => Scope::Reports, Permission::ViewPerformanceEvaluations->value => Scope::Reports], ['manager_id' => Employee::factory()->create()->id]), 'ben', ['work' => false, 'travel' => false, 'performance' => null]],
    'one who sees everyone\'s, on their own page, where their own Evaluations are not listed' => [fn () => employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone, Permission::ViewTravel->value => Scope::Everyone, Permission::ViewPerformanceEvaluations->value => Scope::Everyone]), 'themselves', ['work' => true, 'travel' => true, 'performance' => null]],
    'one who sees only their own, on their own page' => [fn () => employeeWith([Permission::ViewWorkEntries->value => Scope::Own, Permission::ViewTravel->value => Scope::Own]), 'themselves', ['work' => false, 'travel' => false, 'performance' => null]],
]);

it('links a Former Employee\'s Overview to the year of their latest Performance Evaluation, and to the current one when they have none', function () {
    travelTo('2026-11-13 09:00');
    $gone = Employee::factory()->former()->create();
    $none = Employee::factory()->former()->create();
    PerformanceEvaluation::factory()->of($gone)->completed()->create(['year' => 2024]);
    PerformanceEvaluation::factory()->of($gone)->completed()->create(['year' => 2025]);
    $reader = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone, Permission::ViewPerformanceEvaluations->value => Scope::Everyone]);

    actingAs($reader)->get(route('employees.show', $gone))
        ->assertInertia(fn (Assert $page) => $page->where('seeAlso.performance', 2025));
    actingAs($reader)->get(route('employees.show', $none))
        ->assertInertia(fn (Assert $page) => $page->where('seeAlso.performance', 2026));
});
