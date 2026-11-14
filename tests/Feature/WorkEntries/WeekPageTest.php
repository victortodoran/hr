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
        ->where('period', ['mode' => 'week', 'from' => '2026-11-09', 'to' => '2026-11-15', 'query' => ['week' => '2026-11-09']])
        ->where('today', '2026-11-11')
        ->where('days', collect(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15'])
            ->map(fn (string $date): array => ['date' => $date, 'entries' => [], 'hours' => 0, 'time_off' => null, 'nothing_logged' => $date < '2026-11-11', 'copy_from' => null])
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
        ->where('period', ['mode' => 'week', 'from' => '2026-11-02', 'to' => '2026-11-08', 'query' => ['week' => '2026-11-02']])
        ->where('days.1', ['date' => '2026-11-03', 'hours' => 3.5, 'entries' => [
            ['id' => $review->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 2, 'description' => 'Review', 'state' => 'pending', 'can_edit' => true, 'can_delete' => true, 'declined' => null],
            ['id' => $api->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1.5, 'description' => 'API', 'state' => 'pending', 'can_edit' => true, 'can_delete' => true, 'declined' => null],
        ], 'time_off' => null, 'nothing_logged' => false, 'copy_from' => null])
        ->where('days.4', ['date' => '2026-11-06', 'hours' => 0.75, 'entries' => [
            ['id' => $invoices->id, 'assignment_id' => $billing->id, 'project' => 'Billing', 'hours' => 0.75, 'description' => 'Invoices', 'state' => 'pending', 'can_edit' => true, 'can_delete' => true, 'declined' => null],
        ], 'time_off' => null, 'nothing_logged' => false, 'copy_from' => null])
        ->where('days.6', ['date' => '2026-11-08', 'hours' => 0, 'entries' => [], 'time_off' => null, 'nothing_logged' => false, 'copy_from' => '2026-11-06'])
        ->where('assignmentTotals', [
            ['assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 3.5],
            ['assignment_id' => $billing->id, 'project' => 'Billing', 'hours' => 0.75],
        ])
        ->where('total', 4.25));
});

it('shows a month day by day, with totals for the month', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-01', 'minutes' => 60]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-31', 'minutes' => 30]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-02', 'minutes' => 480]);

    $response = actingAs($cara)->get(route('work-entries.index', ['month' => '2026-10']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('period', ['mode' => 'month', 'from' => '2026-10-01', 'to' => '2026-10-31', 'query' => ['month' => '2026-10']])
        ->has('days', 31)
        ->where('days.0.date', '2026-10-01')
        ->where('days.30.hours', 0.5)
        ->where('assignmentTotals', [['assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1.5]])
        ->where('assignments.0.id', $relaunch->id)
        ->where('total', 1.5));
});

it('shows a custom period day by day, up to three months long', function (string $to, int $days) {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index', ['from' => '2026-11-03', 'to' => $to]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('period', ['mode' => 'custom', 'from' => '2026-11-03', 'to' => $to, 'query' => ['from' => '2026-11-03', 'to' => $to]])
        ->has('days', $days)
        ->where('days.0.date', '2026-11-03'));
})->with([
    'a fortnight' => ['2026-11-16', 14],
    'a single day' => ['2026-11-03', 1],
    'three months' => ['2027-02-02', 92],
]);

it('shows a custom period over a month over a week when asked for more than one', function () {
    $cara = recorder();

    actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-02', 'month' => '2026-10', 'from' => '2026-11-03', 'to' => '2026-11-04']))
        ->assertInertia(fn (Assert $page) => $page->where('period.mode', 'custom'));
    actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-02', 'month' => '2026-10']))
        ->assertInertia(fn (Assert $page) => $page->where('period.mode', 'month'));
});

it('shows future weeks and months, offering to record hours on none of their days', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-21']);

    $response = actingAs($cara)->get(route('work-entries.index', ['month' => '2026-12']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('period', ['mode' => 'month', 'from' => '2026-12-01', 'to' => '2026-12-31', 'query' => ['month' => '2026-12']])
        ->where('days.20.time_off', 'paid')
        ->where('days.20.nothing_logged', false)
        ->where('assignments.0.id', $relaunch->id));
});

it('splits the Period into its ISO weeks, each with the dates and hours that fall within the Period', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 120]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-06', 'minutes' => 30]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-30', 'minutes' => 60]);

    $response = actingAs($cara)->get(route('work-entries.index', ['month' => '2026-11']));

    $response->assertInertia(fn (Assert $page) => $page->where('weeks', [
        ['number' => 44, 'from' => '2026-11-01', 'to' => '2026-11-01', 'hours' => 0],
        ['number' => 45, 'from' => '2026-11-02', 'to' => '2026-11-08', 'hours' => 2.5],
        ['number' => 46, 'from' => '2026-11-09', 'to' => '2026-11-15', 'hours' => 0],
        ['number' => 47, 'from' => '2026-11-16', 'to' => '2026-11-22', 'hours' => 0],
        ['number' => 48, 'from' => '2026-11-23', 'to' => '2026-11-29', 'hours' => 0],
        ['number' => 49, 'from' => '2026-11-30', 'to' => '2026-11-30', 'hours' => 1],
    ]));
});

it('numbers the weeks around the new year as ISO weeks', function () {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index', ['from' => '2026-12-28', 'to' => '2027-01-04']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('weeks.0', ['number' => 53, 'from' => '2026-12-28', 'to' => '2027-01-03', 'hours' => 0])
        ->where('weeks.1', ['number' => 1, 'from' => '2027-01-04', 'to' => '2027-01-04', 'hours' => 0]));
});

it('shows each Work Entry\'s state, but not who approved or locked it, and offers to change only Pending ones', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $fay = Employee::factory()->create(['name' => 'Fay']);
    $pending = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'Pending']);
    $approved = WorkEntry::factory()->for($relaunch)->approved($ben)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'Approved', 'approved_at' => '2026-11-10 17:00']);
    $locked = WorkEntry::factory()->for($relaunch)->approved($ben)->locked($fay)->create(['date' => '2026-11-10', 'minutes' => 60, 'description' => 'Locked', 'approved_at' => '2026-11-10 18:00', 'locked_at' => '2026-11-11 08:00']);

    $response = actingAs($cara)->get(route('work-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page->where('days.1.entries', [
        ['id' => $pending->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Pending', 'state' => 'pending', 'can_edit' => true, 'can_delete' => true, 'declined' => null],
        ['id' => $approved->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Approved', 'state' => 'approved', 'can_edit' => false, 'can_delete' => false, 'declined' => null],
        ['id' => $locked->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 1, 'description' => 'Locked', 'state' => 'locked', 'can_edit' => false, 'can_delete' => false, 'declined' => null],
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

it('lets someone see the week of an Employee their Scope covers, as not their own, with Assignments to add to only when they may', function (array $grants, bool $canManage) {
    $top = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith($grants, ['name' => 'Ben', 'manager_id' => $top->id]);
    $cara = Employee::factory()->create(['name' => 'Cara', 'manager_id' => $ben->id]);
    assignmentOf($cara);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $cara->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('employee', ['id' => $cara->id, 'name' => 'Cara'])
        ->where('canManage', $canManage)
        ->where('isOwn', false)
        ->has('assignments', $canManage ? 1 : 0));
})->with([
    'view reports' => [[Permission::ViewWorkEntries->value => Scope::Reports], false],
    'manage reports' => [[Permission::ManageWorkEntries->value => Scope::Reports], true],
]);

it('lets someone who may reopen or lock open the week of anyone that covers, read-only', function (array $grants) {
    $top = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith($grants, ['name' => 'Ben', 'manager_id' => $top->id]);

    $response = actingAs($ben)->get(route('work-entries.index', ['employee' => $top->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('employee', ['id' => $top->id, 'name' => 'Ada'])
        ->where('canManage', false)
        ->where('isOwn', false));
})->with([
    'reopen everyone' => [[Permission::ReopenWorkEntries->value => Scope::Everyone]],
    'lock' => [[Permission::LockWorkEntries->value => null]],
]);

it("opens on the viewer's own week, even when they may see other Employees' weeks", function () {
    $top = Employee::factory()->top()->create(['name' => 'Ada']);
    $ben = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Ben', 'manager_id' => $top->id]);

    $response = actingAs($ben)->get(route('work-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('employee', ['id' => $ben->id, 'name' => 'Ben'])
        ->where('isOwn', true)
        ->missing('employees'));
});

it('refuses a malformed Employee or week', function (string $field, string $value, string $message) {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index', [$field => $value]));

    $response->assertInvalid([$field => $message]);
})->with([
    'Employee not a number' => ['employee', 'Cara', 'The employee field must be an integer.'],
    'week not a date' => ['week', 'next', 'The week field must match the format Y-m-d.'],
]);

it('refuses a malformed month or custom period', function (array $query, string $field, string $message) {
    $cara = recorder();

    $response = actingAs($cara)->get(route('work-entries.index', $query));

    $response->assertInvalid([$field => $message]);
})->with([
    'month not a month' => [['month' => '2026-11-01'], 'month', 'The month field must match the format Y-m.'],
    'From without To' => [['from' => '2026-11-03'], 'to', 'The to field is required when from is present.'],
    'To without From' => [['to' => '2026-11-03'], 'from', 'The from field is required when to is present.'],
    'To before From' => [['from' => '2026-11-03', 'to' => '2026-11-02'], 'to', 'The to field must be a date after or equal to from.'],
    'longer than three months' => [['from' => '2026-11-03', 'to' => '2027-02-03'], 'to', 'A custom period can be at most 3 months long.'],
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
