<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryCopyController;
use App\Http\Requests\WorkEntries\CopyWorkEntriesRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\travelTo;

covers(WorkEntryCopyController::class, CopyWorkEntriesRequest::class, WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('copies every Work Entry of the latest earlier day with any onto the day as Pending ones, back on the same Period', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    $acme = assignmentOf($cara, 'Acme');
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'minutes' => 60, 'description' => 'Older day']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-12', 'minutes' => 300, 'description' => 'Checkout API']);
    WorkEntry::factory()->for($acme)->create(['date' => '2026-11-12', 'minutes' => 90, 'description' => 'Team meeting']);

    $response = actingAs($cara)->post(route('employees.work-entry-copies.store', ['employee' => $cara, 'month' => '2026-11']), ['date' => '2026-11-13']);

    $response->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'month' => '2026-11']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Copied 2 Work Entries from Thu 12.']);
    expect(WorkEntry::query()->whereDate('date', '2026-11-13')->orderBy('id')->get(['assignment_id', 'minutes', 'description', 'state'])->toArray())->toBe([
        ['assignment_id' => $relaunch->id, 'minutes' => 300, 'description' => 'Checkout API', 'state' => 'pending'],
        ['assignment_id' => $acme->id, 'minutes' => 90, 'description' => 'Team meeting', 'state' => 'pending'],
    ]);
});

it('goes back to the Calendar when copying from it', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-12']);

    actingAs($cara)->post(route('employees.work-entry-copies.store', ['employee' => $cara, 'tab' => 'calendar', 'month' => '2026-11']), ['date' => '2026-11-13'])
        ->assertRedirect(route('work-entries.index', ['employee' => $cara->id, 'tab' => 'calendar', 'month' => '2026-11']));
});

it('looks back across a weekend, and says one Work Entry in the singular', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-06', 'description' => 'Friday work']);

    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-09'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Copied 1 Work Entry from Fri 6.']);

    expect(WorkEntry::query()->whereDate('date', '2026-11-09')->sole()->description)->toBe('Friday work');
});

it('looks back at most 7 days', function (string $latest, int $count) {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']))->create(['date' => $latest]);

    $response = actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13']);

    assertDatabaseCount('work_entries', $count);
    if ($count === 1) {
        $response->assertSessionHasErrors(['date' => 'Cara has no Work Entries in the 7 days before 13 Nov 2026 to copy.']);
    }
})->with([
    'seven days before' => ['2026-11-06', 2],
    'eight days before' => ['2026-11-05', 1],
]);

it('leaves out Work Entries on Assignments that don\'t run on the day, and refuses when that leaves none', function () {
    $cara = recorder();
    $ended = assignmentOf($cara, 'Ended', ['start_date' => '2026-10-01', 'end_date' => '2026-11-12']);
    WorkEntry::factory()->for($ended)->create(['date' => '2026-11-12']);

    $refused = actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13']);

    $refused->assertSessionHasErrors(['date' => 'None of Cara\'s Work Entries on Thu 12 are on an Assignment that runs on 13 Nov 2026.']);
    assertDatabaseCount('work_entries', 1);

    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-12', 'description' => 'Still running']);

    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Copied 1 Work Entry from Thu 12.']);
    expect(WorkEntry::query()->whereDate('date', '2026-11-13')->sole()->description)->toBe('Still running');
});

it('refuses to copy onto a day still to come or a day of Time Off', function (string $date, string $message) {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-12', 'last_date' => '2026-11-12']);

    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => $date])
        ->assertSessionHasErrors(['date' => $message]);

    assertDatabaseCount('work_entries', 1);
})->with([
    'tomorrow' => ['2026-11-14', 'Work Entries can\'t be recorded for days still to come.'],
    'Time Off' => ['2026-11-12', 'Cara has Time Off on 12 Nov 2026, so nothing is copied onto it.'],
]);

it('copies only onto a day with no Work Entries yet, so copying twice copies nothing more', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-12']);

    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13'])->assertSessionHasNoErrors();
    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13'])
        ->assertSessionHasErrors(['date' => 'Cara already has Work Entries on 13 Nov 2026; copying only fills a day with none.']);

    assertDatabaseCount('work_entries', 2);
});

it('names the month of a source day in another month', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']))->create(['date' => '2026-10-30']);

    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-02'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Copied 1 Work Entry from Fri 30 Oct.']);
});

it('requires a valid date', function (mixed $date) {
    $cara = recorder();

    actingAs($cara)->post(route('employees.work-entry-copies.store', $cara), ['date' => $date])
        ->assertSessionHasErrors('date');
})->with([null, 'yesterday', '13/11/2026']);

it('lets only those who may record hours for the Employee copy them', function () {
    $cara = Employee::factory()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-12']);
    $viewer = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]);
    $manager = employeeWith([Permission::ManageWorkEntries->value => Scope::Everyone]);

    actingAs($viewer)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13'])->assertForbidden();
    assertDatabaseCount('work_entries', 1);

    actingAs($manager)->post(route('employees.work-entry-copies.store', $cara), ['date' => '2026-11-13'])->assertSessionHasNoErrors();
    assertDatabaseCount('work_entries', 2);
});

it('tells each empty day it can be added to which earlier day its Work Entries would be copied from', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-06']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-12', 'last_date' => '2026-11-12']);

    actingAs($cara)->get(route('work-entries.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.0.copy_from', '2026-11-06')
            ->where('days.1.copy_from', null)
            ->where('days.2.copy_from', '2026-11-10')
            ->where('days.3.copy_from', null)
            ->where('days.4.copy_from', '2026-11-10')
            ->where('days.5.copy_from', null));

    actingAs(employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]))
        ->get(route('work-entries.index', ['employee' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page->where('days.4.copy_from', null));
});
