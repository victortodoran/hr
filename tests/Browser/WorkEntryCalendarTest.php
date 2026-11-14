<?php

use App\Http\Controllers\WorkEntryController;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(WorkEntryController::class);

beforeEach(function () {
    travelTo('2026-11-13 09:00');
});

it('shows a month as a calendar, a line per Work Entry with a dot for its state, kept in the URL, sharing its month with the List and hiding weekends with it', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-10-01']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-10-07', 'minutes' => 300, 'description' => 'Fixing the login']);
    WorkEntry::factory()->for(assignmentOf($cara, 'Acme', ['start_date' => '2026-10-01']))->approved()->create(['date' => '2026-10-07', 'minutes' => 150, 'description' => 'Team meeting']);

    actingAs($cara);

    visit(route('work-entries.index', ['week' => '2026-10-06']))
        ->click('internal:role=tab[name="Calendar"i]')
        ->assertQueryStringHas('tab', 'calendar')
        ->assertQueryStringHas('month', '2026-10')
        ->assertSee('W41')
        ->assertSee('7.5 h')
        ->assertSeeIn('[data-date="2026-10-07"]', 'Fixing the login')
        ->assertSeeIn('[data-date="2026-10-07"]', 'Team meeting')
        ->assertPresent('[data-date="2026-10-07"] [data-state="pending"]')
        ->assertSeeIn('[aria-label="Legend"]', 'Pending')
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

it('fits a phone, showing each day\'s hours and a dot per state instead of its Work Entry lines', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->approved()->create(['date' => '2026-11-03', 'minutes' => 450, 'description' => 'A long description of the work']);

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->resize(360, 800)
        ->assertSee('7.5 h')
        ->assertScript('document.querySelector(\'[data-date="2026-11-03"]\').innerText.includes("A long description of the work")', false)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->resize(1280, 800)
        ->assertSeeIn('[data-date="2026-11-03"]', 'A long description of the work')
        ->assertNoJavaScriptErrors();
});

it('shows at most three Work Entries on a day, then how many more, and shades weekends', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara);
    foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $description) {
        WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-03', 'minutes' => 60, 'description' => $description]);
    }

    actingAs($cara);

    visit(route('work-entries.index', ['tab' => 'calendar', 'month' => '2026-11']))
        ->assertSeeIn('[data-date="2026-11-03"]', 'Three')
        ->assertDontSeeIn('[data-date="2026-11-03"]', 'Four')
        ->assertSeeIn('[data-date="2026-11-03"]', '+2 more')
        ->assertScript('document.querySelector(\'[data-date="2026-11-07"]\').classList.contains("bg-muted/20")', true)
        ->assertScript('document.querySelector(\'[data-date="2026-11-06"]\').classList.contains("bg-muted/20")', false)
        ->assertNoJavaScriptErrors();
});
