<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryCopyController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryCopyController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('adds, copies and deletes Work Entries from a Calendar day, and moves on to the next empty day', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 300, 'description' => 'Checkout API']);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->assertSeeIn('[aria-label="Legend"]', 'Click a day to add or see its entries')
        ->click('[aria-label="Add or view entries, Wednesday 11 November"]')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-11')
        ->click('Copy entries from Tue 10')
        ->assertSee('Copied 1 Work Entry from Tue 10.')
        ->assertSeeIn('[data-date="2026-11-11"]', 'Checkout API')
        ->assertSeeIn('[data-panel-date="2026-11-11"]', 'Checkout API')
        ->click('Next empty day · Thu 12')
        ->assertPresent('[data-panel-date="2026-11-12"]')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-12')
        ->fill('#work-entry-description-2026-11-12', 'Sprint planning')
        ->fill('#work-entry-hours-2026-11-12', '2')
        ->keys('#work-entry-hours-2026-11-12', 'Enter')
        ->assertSeeIn('[data-date="2026-11-12"]', 'Sprint planning')
        ->assertValue('#work-entry-description-2026-11-12', '')
        ->keys('#work-entry-description-2026-11-12', 'Escape')
        ->assertNotPresent('[data-panel-date="2026-11-12"]')
        ->click('[aria-label="Add or view entries, Tuesday 10 November"]')
        ->click('[data-panel-date="2026-11-10"] [aria-label="Delete Work Entry"]')
        ->click('internal:role=dialog[name="Delete this Work Entry?"s] >> internal:role=button[name="Delete"s]')
        ->assertDontSeeIn('[data-date="2026-11-10"]', 'Checkout API')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->orderBy('date')->pluck('description')->all())->toBe(['Checkout API', 'Sprint planning']);
});

it('says why a day of Time Off or still to come takes no hours', function () {
    $cara = recorder();
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-23', 'last_date' => '2026-11-27']);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->click('[aria-label="View, Monday 23 November"]')
        ->assertSeeIn('[data-panel-date="2026-11-23"]', 'Paid Time Off. No hours to log.')
        ->assertNotPresent('#work-entry-description-2026-11-23')
        ->click('[aria-label="View, Monday 16 November"]')
        ->assertSeeIn('[data-panel-date="2026-11-16"]', 'Hours can\'t be logged on days still to come.')
        ->assertNotPresent('#work-entry-description-2026-11-16')
        ->assertNoJavaScriptErrors();
});

it('shows someone who can only look a day\'s Work Entries, with nothing to add or delete', function () {
    $cara = Employee::factory()->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'description' => 'Checkout API']);

    actingAs(employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]));

    visit(route('work-entries.index', ['employee' => $cara->id, 'tab' => 'calendar', 'month' => '2026-11']))
        ->assertSeeIn('[aria-label="Legend"]', 'Click a day to see its entries')
        ->click('[aria-label="View, Tuesday 10 November"]')
        ->assertSeeIn('[data-panel-date="2026-11-10"]', 'Checkout API')
        ->assertNotPresent('[data-panel-date="2026-11-10"] [aria-label="Delete Work Entry"]')
        ->assertNotPresent('#work-entry-description-2026-11-10')
        ->assertNoJavaScriptErrors();
});

it('opens a day as a sheet from the bottom on a phone', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->resize(360, 800)
        ->click('[aria-label="Add or view entries, Thursday 12 November"]')
        ->assertPresent('[data-slot="sheet-content"] [data-panel-date="2026-11-12"]')
        ->fill('#work-entry-description-2026-11-12', 'From my phone')
        ->fill('#work-entry-hours-2026-11-12', '1')
        ->keys('#work-entry-hours-2026-11-12', 'Enter')
        ->assertSeeIn('[data-panel-date="2026-11-12"]', 'From my phone')
        ->click('Next empty day · Fri 13')
        ->assertPresent('[data-slot="sheet-content"] [data-panel-date="2026-11-13"]')
        ->fill('#work-entry-description-2026-11-13', 'Next day from my phone')
        ->fill('#work-entry-hours-2026-11-13', '1')
        ->keys('#work-entry-hours-2026-11-13', 'Enter')
        ->assertSeeIn('[data-panel-date="2026-11-13"]', 'Next day from my phone')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->where('description', 'Next day from my phone')->sole()->date->toDateString())->toBe('2026-11-13');
});

it('asks before closing a day, or opening another, with unsaved typing in its add form', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->click('[aria-label="Add or view entries, Thursday 12 November"]')
        ->fill('#work-entry-description-2026-11-12', 'Half typed')
        ->keys('#work-entry-description-2026-11-12', 'Escape')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('Next empty day · Fri 13')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('Next empty day · Fri 13')
        ->click('Leave')
        ->assertNotPresent('[data-panel-date="2026-11-12"]')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-13')
        ->click('[aria-label="Previous month"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertDontSee('Leave without saving?')
        ->assertNoJavaScriptErrors();
});
