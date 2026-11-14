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

it('lists a month as one table under its total, folding Time Off and future days, with Edit and Delete side by side, and fits a phone', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch', ['job_title' => 'Tech Lead']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 300, 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for(assignmentOf($cara, 'Acme'))->create(['date' => '2026-11-11', 'minutes' => 450, 'description' => 'Fixing the login']);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'minutes' => 120, 'description' => 'Pairing']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-23', 'last_date' => '2026-11-27']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-11-12', 'last_date' => '2026-11-12']);

    actingAs($cara);

    visit(route('work-entries.index', ['month' => '2026-11']))
        ->assertSeeIn('[data-test="work-entry-summary"]', '14.5 h')
        ->assertSeeIn('[data-test="work-entry-summary"]', 'in November 2026')
        ->assertSeeIn('[data-test="work-entry-summary"]', 'Acme 7.5 h')
        ->assertDontSee('Tech Lead')
        ->assertSeeIn('[data-date="2026-11-11"] [data-test="day-hours"]', '9.5 h')
        ->assertSeeIn('[data-date="2026-11-12"]', 'Paid Time Off')
        ->assertPresent('[data-date="2026-11-12"] [data-test="add-row"]')
        ->assertSeeIn('[data-test="time-off-run"]', 'Mon 23 – Fri 27')
        ->assertSeeIn('[data-test="time-off-run"]', '5 days')
        ->assertScript('document.querySelectorAll(\'[data-test="time-off-run"]\').length', 1)
        ->assertScript('[...document.querySelectorAll(\'[data-test="upcoming-run"]\')].map((run) => run.innerText.split("\n")[0])', ['Sat 14 – Sun 15', 'Mon 16 – Sun 22', 'Sat 28 – Sun 29', 'Mon 30'])
        ->assertPresent('[data-date="2026-11-10"] [aria-label="Edit Work Entry"]')
        ->assertPresent('[data-date="2026-11-10"] [aria-label="Delete Work Entry"]')
        ->click('[data-date="2026-11-10"] [aria-label="Edit Work Entry"]')
        ->assertPresent('[data-date="2026-11-10"] form [aria-label="Delete Work Entry"]')
        ->click('Cancel')
        ->resize(360, 800)
        ->assertSee('Reviewing the API')
        ->assertScript('[...document.querySelectorAll("section > h3")].map((header) => getComputedStyle(header).borderTopWidth)', ['0px', '1px', '1px', '1px', '1px', '1px'])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
