<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\WorkEntryState;
use App\Http\Controllers\WorkEntries\ReopeningController;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryExportController;
use App\Http\Controllers\WorkEntries\WorkEntryListController;
use App\Models\Employee;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryListController::class, ReopeningController::class, WorkEntryController::class, WorkEntryExportController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('filters to Approved Work Entries, then reopens one and leaves the other Approved', function () {
    $finn = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara);
    $toReopen = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-09', 'description' => 'Reviewing the API']);
    $stillApproved = WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'description' => 'Fixing the login']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Writing specs']);

    actingAs($finn);

    visit(route('work-entry-list.index'))
        ->assertSee('Writing specs')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Approved"s]')
        ->assertQueryStringHas('state', 'approved')
        ->assertDontSee('Writing specs')
        ->click('[aria-label="Select Cara\'s Work Entry on Nov 9, 2026"]')
        ->click('Reopen')
        ->assertSee('Reopen 1 Work Entry?')
        ->click('internal:role=dialog >> internal:role=button[name="Reopen"s]')
        ->assertSee('1 Work Entry reopened.')
        ->assertDontSee('Reviewing the API')
        ->assertSee('Fixing the login')
        ->assertNoJavaScriptErrors();

    expect($toReopen->refresh()->state)->toBe(WorkEntryState::Pending)
        ->and($stillApproved->refresh()->state)->toBe(WorkEntryState::Approved);
});

it('keeps Work Entries ticked across pages, reopening them all and staying on a page that still has some', function () {
    $finn = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $relaunch = assignmentOf(Employee::factory()->create(['name' => 'Cara']));
    collect(range(2, 13))->each(fn (int $day) => WorkEntry::factory()->count(3)->for($relaunch)->approved()->create(['date' => "2026-11-{$day}"]));

    actingAs($finn);

    visit(route('work-entry-list.index', ['state' => 'approved']))
        ->click('[role="checkbox"][aria-label^="Select Cara"] >> nth=0')
        ->click('Next')
        ->assertSee('26–36 of 36')
        ->click('[role="checkbox"][aria-label^="Select Cara"] >> nth=0')
        ->assertSee('2 selected')
        ->click('Reopen')
        ->assertSee('Reopen 2 Work Entries?')
        ->click('internal:role=dialog >> internal:role=button[name="Reopen"s]')
        ->assertSee('2 Work Entries reopened.')
        ->assertSee('26–34 of 34')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->where('state', WorkEntryState::Pending)->count())->toBe(2);
});

it('steps back a month, picks a month from the picker, and returns to this month, one Employee at a time', function () {
    $finn = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-09-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Writing specs']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-20', 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-09-15', 'description' => 'Planning the relaunch']);

    actingAs($finn);

    visit(route('work-entry-list.index'))
        ->assertSee('November 2026')
        ->assertSee('Cara (1)')
        ->assertSee('Writing specs')
        ->assertButtonDisabled('[aria-label="Next month"]')
        ->click('[aria-label="Previous month"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertSee('Reviewing the API')
        ->assertDontSee('Writing specs')
        ->click('internal:role=button[name="October 2026"s]')
        ->click('internal:role=menuitem[name="Sep"s]')
        ->assertQueryStringHas('month', '2026-09')
        ->assertSee('Planning the relaunch')
        ->click('This month')
        ->assertSee('Writing specs')
        ->assertNoJavaScriptErrors();
});

it('switches to a week, steps back one, and shows a custom period, keeping any Employee', function () {
    $finn = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $relaunch = assignmentOf(Employee::factory()->create(['name' => 'Cara']), 'Relaunch', ['start_date' => '2026-09-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Writing specs']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-04', 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for(assignmentOf(Employee::factory()->create(['name' => 'Bea']), 'Intranet', ['start_date' => '2026-09-01']))->create(['date' => '2026-10-20', 'description' => 'Planning the intranet']);

    actingAs($finn);

    $page = visit(route('work-entry-list.index', ['employee' => 'all']))
        ->assertSee('Writing specs')
        ->assertSee('Reviewing the API')
        ->click('internal:role=button[name="Week"s]')
        ->assertQueryStringHas('week', '2026-11-13')
        ->assertQueryStringHas('employee', 'all')
        ->assertSee('Writing specs')
        ->assertDontSee('Reviewing the API')
        ->assertButtonDisabled('[aria-label="Next week"]')
        ->click('[aria-label="Previous week"]')
        ->assertSee('Reviewing the API')
        ->assertDontSee('Writing specs')
        ->click('internal:role=button[name="Custom"s]');

    pickDate(pickDate($page, '#period-from', '2026-10-15'), '#period-to', '2026-11-05')
        ->click('Show')
        ->assertQueryStringHas('from', '2026-10-15')
        ->assertSee('Planning the intranet')
        ->assertSee('Reviewing the API')
        ->assertDontSee('Writing specs')
        ->click('internal:role=button[name="Month"s]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertSee('Planning the intranet')
        ->assertDontSee('Reviewing the API')
        ->assertNoJavaScriptErrors();
});

it("opens an Employee's week from the list under their name, while the viewer's own week has no Employee picker", function () {
    $finn = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-11', 'description' => 'Writing specs']);

    actingAs($finn);

    visit(route('work-entries.index'))
        ->assertTitle('Work - '.config('app.name'))
        ->assertNotPresent('[aria-label="Employee"]')
        ->assertNoJavaScriptErrors();

    visit(route('work-entry-list.index'))
        ->click('internal:role=link[name="Cara"s]')
        ->assertSee("Cara's Work Entries")
        ->assertSee('Writing specs')
        ->assertNotPresent('[aria-label="Employee"]')
        ->assertNoJavaScriptErrors();
});

it('shows the total above the list, with Status in its own column, and fits a phone with the Status under the name', function () {
    $finn = employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara, 'Relaunch');
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-10', 'minutes' => 180, 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'minutes' => 90, 'description' => 'Writing specs']);
    WorkEntry::factory()->for($relaunch)->declined(reason: 'Wrong Project')->create(['date' => '2026-11-12', 'minutes' => 60, 'description' => 'Fixing the login']);

    actingAs($finn);

    visit(route('work-entry-list.index'))
        ->assertSeeIn('[data-test="work-entry-summary"]', '4.5 h')
        ->assertSeeIn('[data-test="work-entry-summary"]', 'in 3 matching Work Entries')
        ->assertSeeIn('[data-test="declined-hours"]', '1 h declined, not counted')
        ->assertSeeIn('[data-test="work-entry"]', 'Tue 10')
        ->assertSeeIn('[data-test="decline-reason"]', 'Declined by')
        ->resize(360, 800)
        ->assertSee('Cara')
        ->assertSee('Approved')
        ->assertSee('Wrong Project')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript('[...document.querySelectorAll("table")].every((table) => table.parentElement.scrollWidth <= table.parentElement.clientWidth)', true)
        ->assertNoJavaScriptErrors();
});

it('shows an Employee with Work Entries on only one Project on it, without offering any Project', function () {
    $cara = Employee::factory()->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara, 'Relaunch'))->create(['date' => '2026-11-10']);

    actingAs(employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]));

    visit(route('work-entry-list.index'))
        ->assertSeeIn('[aria-label="Project"]', 'Relaunch')
        ->click('[aria-label="Project"]')
        ->assertPresent('internal:role=option[name="Relaunch"s]')
        ->assertNotPresent('internal:role=option[name="Any Project"s]')
        ->assertNoJavaScriptErrors();
});

it('offers the filtered Work Entries as a CSV or Excel file', function () {
    $dan = employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone], ['name' => 'Dan']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'description' => 'Writing specs']);

    actingAs($dan);

    visit(route('work-entry-list.index'))
        ->assertSee('Writing specs')
        ->click('Export')
        ->assertAttributeContains('internal:role=menuitem[name="CSV"s]', 'href', "/work-entries/export?month=2026-11&employee={$cara->id}&format=csv")
        ->assertAttributeContains('internal:role=menuitem[name="Excel"s]', 'href', "/work-entries/export?month=2026-11&employee={$cara->id}&format=xlsx")
        ->assertNoJavaScriptErrors();
});

it('disables Export when no Work Entries match', function () {
    actingAs(employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]));

    visit(route('work-entry-list.index'))
        ->assertSee('No Work Entries in this period.')
        ->assertButtonDisabled('Export')
        ->assertNoJavaScriptErrors();
});
