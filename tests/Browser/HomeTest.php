<?php

use App\Http\Controllers\HomeController;
use App\Models\TimeOff;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(HomeController::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('lands an Employee on Home after signing in, showing their month, Paid Days and next Time Off', function () {
    $cara = recorder(['paid_days' => 20]);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'minutes' => 90]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-23']);

    visit(route('login'))
        ->fill('email', $cara->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/home')
        ->assertSee('Hours this month')
        ->assertSee('1.5 h')
        ->assertSee('Paid Days left in 2026')
        ->assertSee('Paid Days left in 2027')
        ->assertSee('17')
        ->assertSee('Next: Dec 21 – 23, 2026')
        ->assertNotPresent('[data-test="waiting-for-you"]')
        ->click('Open Work')
        ->assertQueryStringHas('month', '2026-11')
        ->assertNoJavaScriptErrors();
});

it('shows a Manager what waits for them on Home, a row per kind opening its Approvals tab', function () {
    $ada = recorder(['name' => 'Ada']);
    $ben = recorder(['name' => 'Ben', 'manager_id' => $ada->id]);
    WorkEntry::factory()->for(assignmentOf($ben))->count(2)->create(['date' => '2026-11-10']);
    TimeOff::factory()->for($ben)->create();

    actingAs($ada);

    visit(route('landing'))
        ->assertSeeIn('[data-test="waiting-for-you"]', 'Work Entries')
        ->assertSeeIn('[data-test="waiting-for-you"]', 'Time Off')
        ->assertDontSee('Travel Requests')
        ->click('internal:role=link[name="Time Off 1"s]')
        ->assertQueryStringHas('tab', 'time-off')
        ->assertNoJavaScriptErrors();
});

it('offers one way into Time Off on Home when none is upcoming', function () {
    $cara = recorder(['paid_days' => 20]);

    actingAs($cara);

    visit(route('landing'))
        ->assertSee('Paid Days left in 2026')
        ->assertSee('You have no upcoming Time Off.')
        ->click('Request Time Off')
        ->assertPathIs('/time-off/create')
        ->assertNoJavaScriptErrors();
});

it('shows an Employee their hours this year month by month on a phone, opening a month\'s Work Entries from its bar', function () {
    $cara = recorder(['start_date' => '2026-09-14']);
    $relaunch = assignmentOf($cara, 'Relaunch', ['start_date' => '2026-09-14']);
    WorkEntry::factory()->for($relaunch)->approved()->create(['date' => '2026-10-05', 'minutes' => 480]);
    WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'minutes' => 90]);

    actingAs($cara);

    visit(route('landing'))
        ->resize(390, 844)
        ->assertSee('Hours this year')
        ->assertSeeIn('[data-test="hours-this-year"]', '9.5 h')
        ->assertCount('[data-test="mini-bar"]', 3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="October 2026: 8 h"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertNoJavaScriptErrors();
});

it('tells an Employee with no hours this month or year that none count yet, opening this month\'s Work Entries', function () {
    actingAs(recorder());

    visit(route('landing'))
        ->assertSee('No hours this month yet. Declined ones don\'t count.')
        ->assertSee("You haven't recorded any hours this year.")
        ->assertNotPresent('[data-test="mini-bar"]')
        ->click('Open Work')
        ->assertQueryStringHas('month', '2026-11')
        ->assertNoJavaScriptErrors();
});

it('shows an Employee what of theirs was declined on Home, opening a Work Entry\'s week from it', function () {
    $cara = recorder();
    $relaunch = assignmentOf($cara, 'Relaunch');
    WorkEntry::factory()->for($relaunch)->declined(reason: 'Split by task')->create(['date' => '2026-11-10', 'minutes' => 90]);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-21', 'decline_reason' => 'Launch week']);

    actingAs($cara);

    visit(route('landing'))
        ->resize(390, 844)
        ->assertSee('Needs your attention')
        ->assertSee('Split by task')
        ->assertSee('Launch week')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=link[name=/^Work Entry/]')
        ->assertQueryStringHas('week', '2026-11-10')
        ->assertNoJavaScriptErrors();
});

it('shows a widget\'s first three rows on a phone, the rest after View all', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->declined()->count(5)->sequence(fn ($sequence) => ['date' => '2026-11-0'.($sequence->index + 2)])->create();
    $visibleRows = "[...document.querySelectorAll('[data-test=\"needs-attention\"] li')].filter((row) => row.checkVisibility()).length";

    actingAs($cara);

    visit(route('landing'))
        ->resize(390, 844)
        ->assertScript($visibleRows, 3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('View all 5')
        ->assertScript($visibleRows, 5)
        ->assertNoJavaScriptErrors();
});

it('tells an Employee with no Manager why Home shows no Work, on a phone', function () {
    actingAs(recorder(['manager_id' => null]));

    visit(route('landing'))
        ->resize(390, 844)
        ->assertSee("You have no Manager, so you don't record Work Entries.")
        ->assertDontSee('Hours this month')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
