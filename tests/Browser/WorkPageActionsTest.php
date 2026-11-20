<?php

use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryExportController;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class, WorkEntryExportController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('opens today to add a Work Entry from the header, on the List and on the Calendar, from another Period too', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index', ['week' => '2026-10-05']))
        ->click('internal:role=button[name="Add Work Entry"s]')
        ->assertQueryStringMissing('week')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-13')
        ->click('internal:role=button[name="Calendar"s]')
        ->click('internal:role=button[name="Add Work Entry"s]')
        ->assertPresent('[data-panel-date="2026-11-13"]')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-13')
        ->assertNoJavaScriptErrors();
});

it('exports the Employee\'s own Work Entries for the Period shown', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10']);

    actingAs($cara);

    visit(route('work-entries.index', ['month' => '2026-11']))
        ->click('internal:role=button[name="Export"s]')
        ->assertAttributeContains('internal:role=menuitem[name="CSV"s]', 'href', "/work-entries/export?month=2026-11&employee={$cara->id}&format=csv")
        ->assertNoJavaScriptErrors();
});

it('tells an Employee without a Manager why they record no Work Entries, on a phone too', function () {
    actingAs(recorder(['manager_id' => null]));

    visit(route('work-entries.index'))
        ->resize(390, 844)
        ->assertSee('You have no Manager, so you don\'t record Work Entries: nobody could approve them.')
        ->assertNotPresent('internal:role=button[name="Add Work Entry"s]')
        ->assertNotPresent('internal:role=button[name="Export"s]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
