<?php

use App\Http\Controllers\HomeController;
use App\Models\PaidDays;
use App\Models\TimeOff;
use App\Models\WorkEntry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(HomeController::class);

beforeEach(function () {
    travelTo('2026-11-11 09:00');
});

it('lands an Employee on Home after signing in, showing their week, Paid Days and next Time Off', function () {
    $cara = recorder();
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-10', 'minutes' => 90]);
    PaidDays::factory()->for($cara)->create(['year' => 2026, 'days' => 20]);
    TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-21', 'last_date' => '2026-12-23']);

    visit(route('login'))
        ->fill('email', $cara->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/home')
        ->assertSee('Hours this week')
        ->assertSee('1.5 h')
        ->assertSee('Paid Days left in 2026')
        ->assertSee('17')
        ->assertSee('Next Time Off')
        ->assertDontSee('Waiting for you')
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
    $cara = recorder();
    PaidDays::factory()->for($cara)->create(['year' => 2026, 'days' => 20]);

    actingAs($cara);

    visit(route('landing'))
        ->assertSee('Paid Days left in 2026')
        ->assertSee('You have no upcoming Time Off.')
        ->assertSee('Request Time Off')
        ->assertDontSee('Open Time Off')
        ->assertNoJavaScriptErrors();
});
