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

it('adds Work Entries day after day by keyboard, skipping Time Off, and changes one in place', function () {
    $cara = recorder();
    $assignment = assignmentOf($cara);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-10', 'last_date' => '2026-11-10']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->fill('#work-entry-hours-2026-11-09', '7.5')
        ->fill('#work-entry-description-2026-11-09', 'Reviewing the API')
        ->keys('#work-entry-description-2026-11-09', 'Enter')
        ->assertSee('Reviewing the API')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-11')
        ->fill('#work-entry-hours-2026-11-11', '8')
        ->fill('#work-entry-description-2026-11-11', 'Fixing the login')
        ->keys('#work-entry-description-2026-11-11', 'Enter')
        ->assertSee('Fixing the login')
        ->assertScript('document.activeElement.id', 'work-entry-hours-2026-11-12')
        ->click('Edit')
        ->fill('#work-entry-hours-'.WorkEntry::query()->where('description', 'Reviewing the API')->sole()->id, '6')
        ->click('Save')
        ->assertDontSee('Save')
        ->assertSee('6 h')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->orderBy('date')->get(['assignment_id', 'date', 'minutes', 'description'])->toArray())->toBe([
        ['assignment_id' => $assignment->id, 'date' => '2026-11-09T00:00:00.000000Z', 'minutes' => 360, 'description' => 'Reviewing the API'],
        ['assignment_id' => $assignment->id, 'date' => '2026-11-11T00:00:00.000000Z', 'minutes' => 480, 'description' => 'Fixing the login'],
    ]);
});

it('asks before leaving the week with an unsaved Work Entry', function () {
    $cara = recorder();
    assignmentOf($cara);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->fill('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[aria-label="Previous week"]')
        ->assertSee('You have an unsaved Work Entry. Leave anyway?')
        ->click('Stay')
        ->assertValue('#work-entry-description-2026-11-12', 'Half typed')
        ->click('[aria-label="Previous week"]')
        ->click('Leave')
        ->assertSee('Nov 2, 2026')
        ->assertNoJavaScriptErrors();
});

it('forgets unsaved changes to a Work Entry deleted while open', function () {
    $cara = recorder();
    $entry = WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'description' => 'Reviewing the API']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->click('Edit')
        ->fill('#work-entry-hours-'.$entry->id, '6')
        ->click('Delete')
        ->click('internal:role=dialog[name="Delete this Work Entry?"s] >> internal:role=button[name="Delete"s]')
        ->assertDontSee('Reviewing the API')
        ->click('[aria-label="Previous week"]')
        ->assertSee('Nov 2, 2026')
        ->assertDontSee('Leave without saving?')
        ->assertNoJavaScriptErrors();

    expect($entry->fresh())->toBeNull();
});
