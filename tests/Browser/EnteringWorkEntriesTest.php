<?php

use App\Http\Controllers\WorkEntryController;
use App\Models\TimeOff;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('adds Work Entries one day at a time, staying on the day, moving on with Next day past Time Off, and changes one in place', function () {
    $cara = recorder();
    $assignment = assignmentOf($cara);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-10', 'last_date' => '2026-11-10']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->assertNotPresent('#work-entry-description-2026-11-09')
        ->click('[data-date="2026-11-09"] [data-test="add-row"]')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-09')
        ->fill('#work-entry-description-2026-11-09', 'Reviewing the API')
        ->fill('#work-entry-hours-2026-11-09', '5')
        ->keys('#work-entry-hours-2026-11-09', 'Enter')
        ->assertSee('Reviewing the API')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-09')
        ->assertValue('#work-entry-description-2026-11-09', '')
        ->fill('#work-entry-description-2026-11-09', 'Pairing')
        ->fill('#work-entry-hours-2026-11-09', '2.5')
        ->keys('#work-entry-description-2026-11-09', 'Enter')
        ->assertSee('Pairing')
        ->click('Next day · Wed 11')
        ->assertNotPresent('#work-entry-description-2026-11-09')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-11')
        ->fill('#work-entry-description-2026-11-11', 'Fixing the login')
        ->fill('#work-entry-hours-2026-11-11', '8')
        ->keys('#work-entry-description-2026-11-11', 'Enter')
        ->assertSee('Fixing the login')
        ->keys('#work-entry-description-2026-11-11', 'Escape')
        ->assertNotPresent('#work-entry-description-2026-11-11')
        ->click('[data-date="2026-11-11"] [aria-label="Edit Work Entry"]')
        ->keys('#work-entry-hours-'.WorkEntry::query()->where('description', 'Fixing the login')->sole()->id, 'Escape')
        ->assertDontSee('Save')
        ->click('[data-date="2026-11-11"] [aria-label="Edit Work Entry"]')
        ->fill('#work-entry-hours-'.WorkEntry::query()->where('description', 'Fixing the login')->sole()->id, '6')
        ->keys('#work-entry-hours-'.WorkEntry::query()->where('description', 'Fixing the login')->sole()->id, 'Escape')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->click('Save')
        ->assertDontSee('Save')
        ->assertSee('6 h')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->orderBy('id')->get(['assignment_id', 'date', 'minutes', 'description'])->toArray())->toBe([
        ['assignment_id' => $assignment->id, 'date' => '2026-11-09T00:00:00.000000Z', 'minutes' => 300, 'description' => 'Reviewing the API'],
        ['assignment_id' => $assignment->id, 'date' => '2026-11-09T00:00:00.000000Z', 'minutes' => 150, 'description' => 'Pairing'],
        ['assignment_id' => $assignment->id, 'date' => '2026-11-11T00:00:00.000000Z', 'minutes' => 360, 'description' => 'Fixing the login'],
    ]);
});

it('adds another Work Entry to a day that has one with its + button, which keeps its place on a day it can\'t edit', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-11-11', 'description' => 'Approved']);
    $plusLeft = fn (string $day): string => "Math.round(document.querySelector('[aria-label=\"Add another entry on {$day}\"]').getBoundingClientRect().left)";

    actingAs($cara);

    $page = visit(route('work-entries.index'));

    expect($page->script($plusLeft('Tuesday 10 November')))->toBe($page->script($plusLeft('Wednesday 11 November')));

    $page
        ->assertNotPresent('[data-date="2026-11-11"] [aria-label="Edit Work Entry"]')
        ->assertNotPresent('[data-date="2026-11-11"] [aria-label="Delete Work Entry"]')
        ->assertNotPresent('[data-date="2026-11-10"] [data-test="add-row"]')
        ->click('[aria-label="Add another entry on Tuesday 10 November"]')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-10')
        ->assertNoJavaScriptErrors();
});

it('copies the Work Entries of the latest earlier day onto a day from its add form', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-12', 'minutes' => 300, 'description' => 'Checkout API']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-12', 'minutes' => 60, 'description' => 'Team meeting']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->click('[data-date="2026-11-09"] [data-test="add-row"]')
        ->assertDontSee('Copy entries from')
        ->click('[data-date="2026-11-13"] [data-test="add-row"]')
        ->click('Copy entries from Thu 12')
        ->assertSee('Copied 2 Work Entries from Thu 12.')
        ->assertSeeIn('[data-date="2026-11-13"]', 'Checkout API')
        ->assertSeeIn('[data-date="2026-11-13"]', 'Team meeting')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->whereDate('date', '2026-11-13')->count())->toBe(2);
});

it('asks before leaving the week, or opening another day, with an unsaved Work Entry', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->click('[data-date="2026-11-12"] [data-test="add-row"]')
        ->fill('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[data-date="2026-11-11"] [data-test="add-row"]')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[aria-label="Previous week"]')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->keys('#work-entry-description-2026-11-12', 'Escape')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[data-date="2026-11-11"] [data-test="add-row"]')
        ->click('Leave')
        ->assertNotPresent('#work-entry-description-2026-11-12')
        ->assertScript('document.activeElement.id', 'work-entry-description-2026-11-11')
        ->click('Month')
        ->assertQueryStringHas('month', '2026-11')
        ->assertNotPresent('#work-entry-description-2026-11-11')
        ->click('[aria-label="Previous month"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertDontSee('Leave without saving?')
        ->assertNoJavaScriptErrors();
});

it('forgets unsaved changes to a Work Entry deleted while open', function () {
    $cara = recorder();
    $entry = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'description' => 'Reviewing the API']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->click('[aria-label="Edit Work Entry"]')
        ->fill('#work-entry-hours-'.$entry->id, '6')
        ->click('[aria-label="Delete Work Entry"]')
        ->click('internal:role=dialog[name="Delete this Work Entry?"s] >> internal:role=button[name="Delete"s]')
        ->assertDontSee('Reviewing the API')
        ->click('[aria-label="Previous week"]')
        ->assertSee('Nov 2, 2026')
        ->assertDontSee('Leave without saving?')
        ->assertNoJavaScriptErrors();

    expect($entry->fresh())->toBeNull();
});
