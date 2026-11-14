<?php

use App\Http\Controllers\WorkEntryController;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('switches between a week, a month and a custom period, kept in the URL, and records hours in a month without leaving it', function () {
    $cara = recorder();
    assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    WorkEntry::factory()->for($cara->assignments()->sole())->create(['date' => '2026-10-20', 'description' => 'Specs in October']);

    actingAs($cara);

    visit(route('work-entries.index'))
        ->assertSee('Nov 9, 2026')
        ->click('Month')
        ->assertQueryStringHas('month', '2026-11')
        ->assertPresent('#work-entry-hours-2026-11-01')
        ->assertPresent('#work-entry-hours-2026-11-13')
        ->fill('#work-entry-hours-2026-11-03', '2')
        ->fill('#work-entry-description-2026-11-03', 'Reviewing the API')
        ->keys('#work-entry-description-2026-11-03', 'Enter')
        ->assertSee('Reviewing the API')
        ->assertQueryStringHas('month', '2026-11')
        ->click('[aria-label="Previous month"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertSee('Specs in October')
        ->click('Custom')
        ->assertQueryStringHas('from', '2026-10-01')
        ->assertQueryStringHas('to', '2026-10-31')
        ->fill('#period-from', '2026-10-15')
        ->fill('#period-to', '2026-10-21')
        ->click('Show')
        ->assertQueryStringHas('from', '2026-10-15')
        ->assertSee('Specs in October')
        ->assertNotPresent('[aria-label="Previous week"]')
        ->click('Week')
        ->assertQueryStringHas('week', '2026-10-15')
        ->assertSee('Oct 12, 2026')
        ->assertNoJavaScriptErrors();
});

it('says why a custom period longer than three months is refused', function () {
    actingAs(recorder());

    visit(route('work-entries.index', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->fill('#period-from', '2026-06-01')
        ->click('Show')
        ->assertSee('A custom period can be at most 3 months long.')
        ->assertNoJavaScriptErrors();
});
