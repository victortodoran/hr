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
        ->assertSee('17')
        ->assertSee('Next Time Off')
        ->assertDontSee('Waiting for you')
        ->click('Open My Work Entries')
        ->assertQueryStringHas('month', '2026-11')
        ->assertNoJavaScriptErrors();
});

it('shows a Manager what waits for them on Home', function () {
    $ada = recorder(['name' => 'Ada']);
    $ben = recorder(['name' => 'Ben', 'manager_id' => $ada->id]);
    WorkEntry::factory()->for(assignmentOf($ben))->create(['date' => '2026-11-10']);
    TimeOff::factory()->for($ben)->create();

    actingAs($ada);

    visit(route('landing'))
        ->assertSee('Waiting for you')
        ->assertSee('1 Pending Work Entry')
        ->assertSee('1 Pending Time Off')
        ->assertNoJavaScriptErrors();
});

it('offers one way into Time Off on Home when none is upcoming', function () {
    $cara = recorder(['paid_days' => 20]);

    actingAs($cara);

    visit(route('landing'))
        ->assertSee('Paid Days left in 2026')
        ->assertSee('You have no upcoming Time Off.')
        ->assertSee('Request Time Off')
        ->assertDontSee('Open Time Off')
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
        ->assertSee('1.5 h Pending')
        ->assertCount('[data-test="month-of-hours"]', 3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[aria-label="October 2026: 8 h"]')
        ->assertQueryStringHas('month', '2026-10')
        ->assertNoJavaScriptErrors();
});

it('tells an Employee with no hours this year that none are recorded yet', function () {
    actingAs(recorder());

    visit(route('landing'))
        ->assertSee('Hours this year')
        ->assertSee("You haven't recorded any hours this year.")
        ->assertNotPresent('[data-test="month-of-hours"]')
        ->assertNoJavaScriptErrors();
});
