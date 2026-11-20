<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Requests\WorkEntries\WorkEntryRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryRequest::class, WorkEntryPolicy::class, WorkEntry::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('lets an Employee record hours on their own Assignment and see them Pending on that week', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['hours' => '2.75']));

    $response->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'week' => '2026-11-09']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Work Entry added.']);
    assertDatabaseHas('work_entries', ['assignment_id' => $relaunch->id, 'date' => '2026-11-10', 'minutes' => 165, 'description' => 'Reviewing the API', 'state' => 'pending', 'approved_by' => null]);
    actingAs($cara)->get(route('work-entries.index', ['week' => '2026-11-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-entries/Index')
            ->where('employee', ['id' => $cara->id, 'name' => 'Cara'])
            ->where('period', ['mode' => 'week', 'from' => '2026-11-09', 'to' => '2026-11-15', 'query' => ['week' => '2026-11-09']])
            ->where('days.1.date', '2026-11-10')
            ->where('days.1.entries', [
                ['id' => WorkEntry::query()->sole()->id, 'assignment_id' => $relaunch->id, 'project' => 'Relaunch', 'hours' => 2.75, 'description' => 'Reviewing the API', 'state' => 'pending', 'can_edit' => true, 'can_delete' => true, 'declined' => null],
            ])
            ->where('canManage', true));
});

it('refuses hours that are not a positive number of quarter hours up to 24', function (string $hours, string $message) {
    $cara = recorder();
    $relaunch = assignmentOf($cara);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['hours' => $hours]));

    $response->assertInvalid(['hours' => $message]);
    assertDatabaseCount('work_entries', 0);
})->with([
    'nothing' => ['0', 'The hours field must be greater than 0.'],
    'not a quarter hour' => ['1.1', 'Record hours in quarter-hour steps, such as 1.25 or 7.5.'],
    'more than a day' => ['24.25', 'The hours field must not be greater than 24.'],
    'not a number' => ['many', 'The hours field must be a number.'],
]);

it('accepts a whole day of 24 hours', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['hours' => '24']))
        ->assertValid();

    assertDatabaseHas('work_entries', ['minutes' => 1440]);
});

it('refuses a date outside the Assignment', function (?string $endDate, string $date, string $message) {
    $cara = recorder();
    $relaunch = assignmentOf($cara, attributes: ['start_date' => '2026-11-02', 'end_date' => $endDate]);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => $date]));

    $response->assertInvalid(['date' => $message]);
    assertDatabaseCount('work_entries', 0);
})->with([
    'before the Assignment starts' => ['2026-11-06', '2026-11-01', 'Cara\'s Assignment to Relaunch runs from 2 Nov 2026 to 6 Nov 2026, so the date must fall within it.'],
    'after the Assignment ends' => ['2026-11-06', '2026-11-07', 'Cara\'s Assignment to Relaunch runs from 2 Nov 2026 to 6 Nov 2026, so the date must fall within it.'],
    'before an open-ended Assignment starts' => [null, '2026-11-01', 'Cara\'s Assignment to Relaunch starts on 2 Nov 2026, so the date can\'t be before it.'],
]);

it('refuses a date after today', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-12']));

    $response->assertInvalid(['date' => 'Work Entries can\'t be recorded for days still to come.']);
    assertDatabaseCount('work_entries', 0);
});

it('refuses hours on a day of the Employee\'s Pending or Approved Time Off', function (string $state) {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $factory = TimeOff::factory()->for($cara);
    ($state === 'approved' ? $factory->approved() : $factory)->create(['first_date' => '2026-11-09', 'last_date' => '2026-11-10']);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-10']));

    $response->assertInvalid(['date' => 'Cara has Time Off on 10 Nov 2026, so no hours can be recorded that day.']);
    assertDatabaseCount('work_entries', 0);
})->with(['pending', 'approved']);

it('accepts hours on a weekend inside Time Off, next to it, or over Declined, Cancelled or someone else\'s Time Off', function (string $date) {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-06', 'last_date' => '2026-11-09']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-11-10', 'last_date' => '2026-11-10']);
    TimeOff::factory()->for($cara)->cancelled()->create(['first_date' => '2026-11-11', 'last_date' => '2026-11-11']);
    TimeOff::factory()->create(['first_date' => '2026-11-04', 'last_date' => '2026-11-04']);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => $date]))
        ->assertValid();

    assertDatabaseHas('work_entries', ['date' => $date]);
})->with([
    'Saturday inside' => '2026-11-07',
    'day before' => '2026-11-05',
    'Declined' => '2026-11-10',
    'Cancelled' => '2026-11-11',
    'someone else\'s' => '2026-11-04',
]);

it('accepts the first and last day of the Assignment, and today', function (array $period, string $date) {
    $cara = recorder();
    $relaunch = assignmentOf($cara, attributes: $period);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => $date]))
        ->assertValid();

    assertDatabaseHas('work_entries', ['date' => $date]);
})->with([
    'first day' => [['start_date' => '2026-11-02', 'end_date' => '2026-11-06'], '2026-11-02'],
    'last day' => [['start_date' => '2026-11-02', 'end_date' => '2026-11-06'], '2026-11-06'],
    'today' => [['start_date' => '2026-11-02', 'end_date' => null], '2026-11-11'],
]);

it('refuses hours that would take the Employee past 24 hours on the day, across all their Assignments', function (int $onRelaunch, string $hours, string $message) {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for(assignmentOf($cara, 'Billing'))->create(['date' => '2026-11-10', 'minutes' => 20 * 60]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => $onRelaunch]);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-10', 'hours' => $hours]));

    $response->assertInvalid(['hours' => $message]);
    assertDatabaseCount('work_entries', 2);
})->with([
    'quarter hours left' => [165, '1.5', 'Cara already has 22.75 hours on 10 Nov 2026, so at most 1.25 more can be recorded that day.'],
    'whole hours left' => [120, '2.5', 'Cara already has 22 hours on 10 Nov 2026, so at most 2 more can be recorded that day.'],
    'half hours left' => [210, '1', 'Cara already has 23.5 hours on 10 Nov 2026, so at most 0.5 more can be recorded that day.'],
]);

it('counts Approved Work Entries towards the 24 hours on the day', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'minutes' => 12 * 60]);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'minutes' => 11 * 60]);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-10', 'hours' => '2']));

    $response->assertInvalid(['hours' => 'Cara already has 23 hours on 10 Nov 2026, so at most 1 more can be recorded that day.']);
});

it('accepts hours that bring the Employee to exactly 24 on the day, ignoring other days and other Employees', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for(assignmentOf($cara, 'Billing'))->create(['date' => '2026-11-10', 'minutes' => 23 * 60]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-09', 'minutes' => 8 * 60]);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create()))->create(['date' => '2026-11-10', 'minutes' => 8 * 60]);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-10', 'hours' => '1']))
        ->assertValid();

    assertDatabaseCount('work_entries', 4);
});

it('lets an Employee record several Work Entries on the same Assignment and day', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 180, 'description' => 'API work']);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-10', 'hours' => '2', 'description' => 'Code review']))
        ->assertValid();

    assertDatabaseHas('work_entries', ['assignment_id' => $relaunch->id, 'date' => '2026-11-10', 'description' => 'API work']);
    assertDatabaseHas('work_entries', ['assignment_id' => $relaunch->id, 'date' => '2026-11-10', 'description' => 'Code review']);
});

it('refuses a malformed Work Entry', function (string $field, mixed $value, string $message) {
    $cara = recorder();
    $relaunch = assignmentOf($cara);

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, [$field => $value]));

    $response->assertInvalid([$field => $message]);
    assertDatabaseCount('work_entries', 0);
})->with([
    'Assignment not a number' => ['assignment_id', 'Relaunch', 'The Assignment field must be an integer.'],
    'date not a date' => ['date', '10/11/2026', 'The date field must match the format Y-m-d.'],
    'description too long' => ['description', str_repeat('a', 1001), 'The description field must not be greater than 1000 characters.'],
]);

it('refuses an Assignment that belongs to another Employee', function () {
    $cara = recorder();
    $dan = Employee::factory()->create();

    $response = actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm(assignmentOf($dan)));

    $response->assertInvalid(['assignment_id' => 'The selected Assignment is invalid.']);
    assertDatabaseCount('work_entries', 0);
});

it('lets an Employee record hours on their Internal Assignment', function () {
    $cara = recorder();
    $internal = Assignment::factory()->for($cara)->internal()->create(['start_date' => '2025-01-06']);

    actingAs($cara)->post(route('employees.work-entries.store', $cara), workEntryForm($internal, ['description' => 'Training']))
        ->assertValid();

    assertDatabaseHas('work_entries', ['assignment_id' => $internal->id, 'description' => 'Training']);
});

it('lets someone record hours for the Employees their manage work entries Scope covers', function () {
    $ben = employeeWith([Permission::ManageWorkEntries->value => Scope::Reports], ['manager_id' => Employee::factory()->create()->id]);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara);

    actingAs($ben)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch))
        ->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'week' => '2026-11-09']));

    assertDatabaseHas('work_entries', ['assignment_id' => $relaunch->id]);
});

it('forbids recording hours for an Employee outside the Scope, or with only view work entries', function (array $grants) {
    $ben = employeeWith($grants);
    $cara = Employee::factory()->create();

    actingAs($ben)->post(route('employees.work-entries.store', $cara), workEntryForm(assignmentOf($cara)))
        ->assertForbidden();

    assertDatabaseCount('work_entries', 0);
})->with([
    'manage reports, not a report' => [[Permission::ManageWorkEntries->value => Scope::Reports]],
    'view everyone' => [[Permission::ViewWorkEntries->value => Scope::Everyone]],
]);

it('lets someone fix a Former Employee\'s hours up to their Leaving Date', function () {
    $admin = employeeWith([Permission::ManageWorkEntries->value => Scope::Everyone]);
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara', 'leaving_date' => '2026-11-05']);
    $relaunch = assignmentOf($cara, attributes: ['end_date' => '2026-11-05']);

    actingAs($admin)->post(route('employees.work-entries.store', $cara), workEntryForm($relaunch, ['date' => '2026-11-05']))
        ->assertValid();

    assertDatabaseHas('work_entries', ['assignment_id' => $relaunch->id, 'date' => '2026-11-05']);
});
