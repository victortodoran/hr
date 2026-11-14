<?php

use App\Http\Controllers\WorkEntryController;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('shows a month as a calendar, its hours coloured by state, kept in the URL, sharing its month with the List and hiding weekends with it', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-07', 'minutes' => 300]);
    WorkEntry::factory()->for(assignmentOf($cara, 'Acme', ['start_date' => '2026-10-01']))->approved()->create(['date' => '2026-10-07', 'minutes' => 150]);

    actingAs($cara);

    visit(route('work-entries.index', ['week' => '2026-10-06']))
        ->click('internal:role=tab[name="Calendar"i]')
        ->assertQueryStringHas('tab', 'calendar')
        ->assertQueryStringHas('month', '2026-10')
        ->assertSee('W41')
        ->assertSee('7.5 h')
        ->assertSee('Relaunch')
        ->assertSee('Acme')
        ->assertPresent('[data-date="2026-10-07"] [data-state="pending"]')
        ->assertPresent('[data-date="2026-10-07"] [data-state="approved"]')
        ->assertSee('Sun')
        ->assertNotPresent('#work-entry-hours-2026-10-07')
        ->click('[aria-label="Next month"]')
        ->assertQueryStringHas('month', '2026-11')
        ->click('[aria-label="Next month"]')
        ->assertQueryStringHas('month', '2026-12')
        ->click('Show weekends')
        ->assertDontSee('Sun')
        ->click('internal:role=tab[name="List"i]')
        ->assertQueryStringMissing('tab')
        ->assertQueryStringHas('month', '2026-12')
        ->assertNoJavaScriptErrors();
});

it('fits a phone, showing each day\'s hours and a dot per state instead of its Project lines', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara, 'A Project With A Long Name'))->approved()->create(['date' => '2026-11-03', 'minutes' => 450]);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->resize(360, 800)
        ->assertSee('7.5 h')
        ->assertScript('document.querySelector(\'[data-date="2026-11-03"]\').innerText.includes("A Project With A Long Name")', false)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->resize(1280, 800)
        ->assertSeeIn('[data-date="2026-11-03"]', 'A Project With A Long Name')
        ->assertNoJavaScriptErrors();
});
