<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntryController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryPolicy::class, WorkEntry::class, TimeOff::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('opens on the viewer\'s own current week, Monday to Sunday', function () {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('work-entries/Index')
        ->where('employee', ['id' => $cara->id, 'name' => 'Cara'])
        ->where('week', '2026-11-09')
        ->where('today', '2026-11-11')
        ->where('days', collect(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15'])
            ->map(fn (string $date): array => ['date' => $date, 'entries' => [], 'hours' => 0, 'time_off' => null, 'nothing_logged' => $date < '2026-11-11'])
            ->all())
        ->where('total', 0));
});

it('shows the week holding the given day, with totals for each day, each Assignment and the week', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $billing = assignmentOf($cara, 'Billing');
    $review = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 120, 'description' => 'Review']);
    $api = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 90, 'description' => 'API']);
    $invoices = WorkEntry::factory()->for($billing)->create(['date' => '2026-11-06', 'minutes' => 45, 'description' => 'Invoices']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09', 'minutes' => 480]);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->create(['date' => '2026-11-03', 'minutes' => 480]);

    $response = actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-08']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('week', '2026-11-02')
        ->where('days.1', ['date' => '2026-11-03', 'hours' => 3.5, 'entries' => [
            ['id' => $review->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 2, 'description' => 'Review', 'state' => 'pending', 'approved' => null, 'locked' => null, 'can_edit' => true, 'can_delete' => true],
            ['id' => $api->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1.5, 'description' => 'API', 'state' => 'pending', 'approved' => null, 'locked' => null, 'can_edit' => true, 'can_delete' => true],
        ], 'time_off' => null, 'nothing_logged' => false])
        ->where('days.4', ['date' => '2026-11-06', 'hours' => 0.75, 'entries' => [
            ['id' => $invoices->id, 'assignment_id' => $billing->id, 'project' => 'Billing', 'hours' => 0.75, 'description' => 'Invoices', 'state' => 'pending', 'approved' => null, 'locked' => null, 'can_edit' => true, 'can_delete' => true],
        ], 'time_off' => null, 'nothing_logged' => false])
        ->where('days.6', ['date' => '2026-11-08', 'hours' => 0, 'entries' => [], 'time_off' => null, 'nothing_logged' => false])
        ->where('assignmentTotals', [
            ['assignment_id' => $billing->id, 'project' => 'Billing', 'hours' => 0.75],
            ['assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 3.5],
        ])
        ->where('total', 4.25));
});

it('shows who approved and locked each Work Entry, and offers to change only Pending ones', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $fay = Employee::factory()->create(['name' => 'Fay']);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'Pending']);
    $approved = WorkEntry::factory()->for($relaunch)->approved($ben)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'Approved', 'approved_at' => '2026-11-10 17:00']);
    $locked = WorkEntry::factory()->for($relaunch)->approved($ben)->locked($fay)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'Locked', 'approved_at' => '2026-11-10 18:00', 'locked_at' => '2026-11-11 08:00']);

    $response = actingAs($cara)->get(route('work-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page->where('days.1.entries', [
        ['id' => $pending->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Pending', 'state' => 'pending', 'approved' => null, 'locked' => null, 'can_edit' => true, 'can_delete' => true],
        ['id' => $approved->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Approved', 'state' => 'approved', 'approved' => ['by' => 'Ben', 'on' => '2026-11-10'], 'locked' => null, 'can_edit' => false, 'can_delete' => false],
        ['id' => $locked->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Locked', 'state' => 'locked', 'approved' => ['by' => 'Ben', 'on' => '2026-11-10'], 'locked' => ['by' => 'Fay', 'on' => '2026-11-11'], 'can_edit' => false, 'can_delete' => false],
    ]));
});

it('labels weekdays covered by Pending or Approved Time Off as Paid or Unpaid, but not weekends', function () {
    $cara = recorder();
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-10-30', 'last_date' => '2026-11-03']);
    TimeOff::factory()->for($cara)->unpaid()->approved()->create(['first_date' => '2026-11-05', 'last_date' => '2026-11-09']);

    $response = actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-02']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('days.0.time_off', 'paid')
        ->where('days.1.time_off', 'paid')
        ->where('days.2.time_off', null)
        ->where('days.3.time_off', 'unpaid')
        ->where('days.4.time_off', 'unpaid')
        ->where('days.5.time_off', null)
        ->where('days.6.time_off', null));
});

it('labels no day covered by Declined or Cancelled Time Off, nor anyone else\'s', function () {
    $cara = recorder();
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-02', 'last_date' => '2026-11-03']);
    TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-11-04', 'last_date' => '2026-11-04']);
    TimeOff::factory()->approved()->create(['first_date' => '2026-11-05', 'last_date' => '2026-11-06']);

    $response = actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-02']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('days.0.time_off', null)
        ->where('days.1.time_off', null)
        ->where('days.2.time_off', null)
        ->where('days.3.time_off', null)
        ->where('days.4.time_off', null)
        ->where('days.0.nothing_logged', true)
        ->where('days.2.nothing_logged', true)
        ->where('days.4.nothing_logged', true));
});

it('marks a past weekday with no Work Entries and no Time Off as nothing logged, but no other day', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-03']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-04', 'last_date' => '2026-11-04']);

    $response = actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-02']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('days.0.nothing_logged', true)
        ->where('days.1.nothing_logged', false)
        ->where('days.2.nothing_logged', false)
        ->where('days.3.nothing_logged', true)
        ->where('days.5.nothing_logged', false)
        ->where('days.6.nothing_logged', false));
});

it('marks neither today nor days still to come as nothing logged', function () {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('days.1.nothing_logged', true)
        ->where('days.2.nothing_logged', false)
        ->where('days.3.nothing_logged', false));
});

it('marks no day before the Start Date or after the Leaving Date as nothing logged', function () {
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $cara = Employee::factory()->create(['start_date' => '2026-11-03', 'leaving_date' => '2026-11-05']);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id, 'week' => '2026-11-02']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('days.0.nothing_logged', false)
        ->where('days.1.nothing_logged', true)
        ->where('days.3.nothing_logged', true)
        ->where('days.4.nothing_logged', false));
});

it('shows a Manager the Time Off and nothing logged days of their Direct Report\'s week', function () {
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Reports], ['name' => 'Ben']);
    $cara = Employee::factory()->create(['manager_id' => $ben->id, 'start_date' => '2025-01-06']);
    TimeOff::factory()->for($cara)->unpaid()->approved($ben)->create(['first_date' => '2026-11-03', 'last_date' => '2026-11-03']);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id, 'week' => '2026-11-02']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('days.0.nothing_logged', true)
        ->where('days.1.time_off', 'unpaid'));
});

it('offers to change no Work Entry to someone who can only see them', function () {
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $cara = Employee::factory()->create();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id]));

    $response->assertInertia(fn (Assert $page) => $page->where('days.1.entries.0.can_edit', false));
});

it('offers the Employee\'s Assignments that run during the week', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, attributes: ['job_title' => 'Developer', 'start_date' => '2026-11-08', 'end_date' => null]);
    $billing = assignmentOf($cara, 'Billing', ['job_title' => 'Analyst', 'start_date' => '2026-10-01', 'end_date' => '2026-11-02']);
    assignmentOf($cara, 'Ended', ['start_date' => '2026-10-01', 'end_date' => '2026-11-01']);
    assignmentOf($cara, 'Later', ['start_date' => '2026-11-09', 'end_date' => null]);

    $response = actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-02']));

    $response->assertInertia(fn (Assert $page) => $page->where('assignments', [
        ['id' => $billing->id, 'project' => 'Billing', 'job_title' => 'Analyst', 'start_date' => '2026-10-01', 'end_date' => '2026-11-02'],
        ['id' => $relaunch->id, 'project' => 'Relaunch', 'job_title' => 'Developer', 'start_date' => '2026-11-08', 'end_date' => null],
    ]));
});

it('lets someone see the week of an Employee their Scope covers, and pick from those Employees', function (array $grants, bool $canManage) {
    $top = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith($grants, ['name' => 'Ben', 'manager_id' => $top->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['name' => 'Dev', 'manager_id' => $cara->id]);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('employee', ['id' => $cara->id, 'name' => 'Cara'])
        ->where('canManage', $canManage)
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara'], ['id' => $dev->id, 'name' => 'Dev']]));
})->with([
    'view reports' => [[Permission::ViewWorkEntries->value => Scope::Reports], false],
    'manage reports' => [[Permission::ManageWorkEntries->value => Scope::Reports], true],
]);

it('lets someone who may reopen or lock open the week of anyone that covers, read-only, and pick from them', function (array $grants) {
    $top = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith($grants, ['name' => 'Ben', 'manager_id' => $top->id]);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $top->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('employee', ['id' => $top->id, 'name' => 'Ada'])
        ->where('canManage', false)
        ->where('employees', [['id' => $top->id, 'name' => 'Ada'], ['id' => $ben->id, 'name' => 'Ben']]));
})->with([
    'reopen everyone' => [[Permission::ReopenWorkEntries->value => Scope::Everyone]],
    'lock' => [[Permission::LockWorkEntries->value => null]],
]);

it('lists everyone covered by either Work Entries Permission in the picker', function () {
    $top = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone, Permission::ManageWorkEntries->value => Scope::Own], ['name' => 'Ben', 'manager_id' => $top->id]);

    $response = actingAs($ben)->get(route('work-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('canManage', true)
        ->where('employees', [['id' => $top->id, 'name' => 'Ada'], ['id' => $ben->id, 'name' => 'Ben']]));
});

it('offers no picker to someone who can only see their own Work Entries', function () {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page->where('employees', null));
});

it('refuses a malformed Employee or week', function (string $field, string $value, string $message) {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index', [$field => $value]));

    $response->assertInvalid([$field => $message]);
})->with([
    'Employee not a number' => ['employee', 'Cara', 'The employee field must be an integer.'],
    'week not a date' => ['week', 'next', 'The week field must match the format Y-m-d.'],
]);

it('forbids seeing the week of an Employee outside the Scope', function () {
    $ben = employeeWith([Permission::ManageWorkEntries->value => Scope::Reports]);
    $cara = Employee::factory()->create();

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id]));

    $response->assertForbidden();
});

it('forbids an Employee who doesn\'t exist just as one outside the Scope, so neither gives away who exists', function () {
    $ben = employeeWith([Permission::ManageWorkEntries->value => Scope::Reports]);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => Employee::query()->max('id') + 1]));

    $response->assertForbidden();
});

it('forbids the page to someone without either Work Entries Permission', function () {
    $ben = employeeWith([Permission::ViewAssignments->value => Scope::Everyone]);

    $response = actingAs($ben)->get(route('work-entries.index'));

    $response->assertForbidden();
});

it('shows the Work Entries item in the navigation only to those holding a Work Entries Permission', function (array $grants, bool $shown) {
    $ben = employeeWith($grants);

    $response = actingAs($ben)->get(route('profile.edit'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.canSeeWorkEntries', $shown));
})->with([
    'view own' => [[Permission::ViewWorkEntries->value => Scope::Own], true],
    'manage own' => [[Permission::ManageWorkEntries->value => Scope::Own], true],
    'neither' => [[Permission::ViewAssignments->value => Scope::Own], false],
]);
