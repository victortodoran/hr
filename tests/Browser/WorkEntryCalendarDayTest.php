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

it('adds, copies and deletes Work Entries from a Calendar day, stepping between days with the keyboard', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 300, 'description' => 'Checkout API']);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->assertSeeIn('[aria-label="Legend"]', 'Select a day')
        ->click('[data-calendar-day="2026-11-11"]')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-11')
        ->click("Copy Tuesday's Work Entries")
        ->assertSee('Copied 1 Work Entry from Tue 10.')
        ->assertSeeIn('[data-date="2026-11-11"]', 'Checkout API')
        ->assertSeeIn('[data-panel-date="2026-11-11"]', 'Checkout API')
        ->click('[data-panel-date="2026-11-11"] [aria-label="Next day"]')
        ->assertPresent('[data-panel-date="2026-11-12"]')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-12')
        ->fill('#work-entry-description-2026-11-12', 'Sprint planning')
        ->fill('#work-entry-hours-2026-11-12', '2')
        ->keys('#work-entry-hours-2026-11-12', 'Enter')
        ->assertSeeIn('[data-date="2026-11-12"]', 'Sprint planning')
        ->assertValue('#work-entry-description-2026-11-12', '')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-12')
        ->keys('#work-entry-hours-2026-11-12', 'Escape')
        ->assertNotPresent('[data-panel-date="2026-11-12"]')
        ->assertScript('document.activeElement.dataset.calendarDay', '2026-11-12')
        ->keys('[data-calendar-day="2026-11-12"]', 'ArrowLeft')
        ->assertScript('document.activeElement.dataset.calendarDay', '2026-11-11')
        ->keys('[data-calendar-day="2026-11-11"]', 'ArrowLeft')
        ->keys('[data-calendar-day="2026-11-10"]', 'Enter')
        ->assertPresent('[data-panel-date="2026-11-10"]')
        ->click('[data-panel-date="2026-11-10"] [aria-label="More for Checkout API"]')
        ->click('internal:role=menuitem[name="Delete"s]')
        ->click('internal:role=dialog[name="Delete Work Entry"s] >> internal:role=button[name="Delete Work Entry"s]')
        ->assertDontSeeIn('[data-date="2026-11-10"]', 'Checkout API')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->orderBy('date')->pluck('description')->all())->toBe(['Checkout API', 'Sprint planning']);
});

it('says why a day of Time Off or still to come takes no hours', function () {
    $cara = recorder();
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-23', 'last_date' => '2026-11-27']);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->click('[data-calendar-day="2026-11-23"]')
        ->assertSeeIn('[data-panel-date="2026-11-23"]', 'Paid Time Off. No hours to log.')
        ->assertNotPresent('#work-entry-description-2026-11-23')
        ->click('[data-panel-date="2026-11-23"] [aria-label="Close"]')
        ->click('[data-calendar-day="2026-11-16"]')
        ->assertSeeIn('[data-panel-date="2026-11-16"]', 'Hours can\'t be logged on days still to come.')
        ->assertNotPresent('#work-entry-description-2026-11-16')
        ->assertNoJavaScriptErrors();
});

it('shows someone who can only look a day\'s Work Entries, with nothing to add, change or delete', function () {
    $cara = Employee::factory()->create(['name' => 'Cara']);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'description' => 'Checkout API']);

    actingAs(employeeWith([Permission::ViewWorkEntries->value => Scope::Everyone]));

    visit(route('work-entries.index', ['employee' => $cara->id, 'tab' => 'calendar', 'month' => '2026-11']))
        ->click('[data-calendar-day="2026-11-10"]')
        ->assertSeeIn('[data-panel-date="2026-11-10"]', 'Checkout API')
        ->assertNotPresent('[data-panel-date="2026-11-10"] [aria-label="More for Checkout API"]')
        ->assertNotPresent('#work-entry-description-2026-11-10')
        ->assertNoJavaScriptErrors();
});

it('opens a day from the week agenda as a sheet from the bottom on a phone, adding with its sticky button', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->resize(390, 844)
        ->click('[data-agenda-day="2026-11-12"]')
        ->assertPresent('[data-slot="sheet-content"] [data-panel-date="2026-11-12"]')
        ->fill('#work-entry-description-2026-11-12', 'From my phone')
        ->fill('#work-entry-hours-2026-11-12', '1')
        ->keys('#work-entry-hours-2026-11-12', 'Enter')
        ->assertSeeIn('[data-panel-date="2026-11-12"]', 'From my phone')
        ->click('[data-slot="sheet-content"] [aria-label="Next day"]')
        ->assertPresent('[data-slot="sheet-content"] [data-panel-date="2026-11-13"]')
        ->fill('#work-entry-description-2026-11-13', 'Next day from my phone')
        ->fill('#work-entry-hours-2026-11-13', '1')
        ->click('[data-panel-date="2026-11-13"] >> internal:role=button[name="Add Work Entry"s]')
        ->assertSeeIn('[data-panel-date="2026-11-13"]', 'Next day from my phone')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->where('description', 'Next day from my phone')->sole()->date->toDateString())->toBe('2026-11-13');
});

it('asks before closing a day, or opening another, with unsaved typing in its add form', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->click('[data-calendar-day="2026-11-12"]')
        ->fill('#work-entry-description-2026-11-12', 'Half typed')
        ->keys('#work-entry-description-2026-11-12', 'Escape')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[data-panel-date="2026-11-12"] [aria-label="Next day"]')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[data-panel-date="2026-11-12"] [aria-label="Next day"]')
        ->click('Leave')
        ->assertNotPresent('[data-panel-date="2026-11-12"]')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-13')
        ->click('[aria-label="Previous month"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertDontSee('Leave without saving?')
        ->assertNoJavaScriptErrors();
});
