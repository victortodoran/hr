<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\TimeOff\TimeOffListController;
use App\Models\Employee;
use App\Models\TimeOff;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffListController::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

it('opens Time Off from Manage, narrows it to a state, and goes to an Employee\'s Time Off tab', function () {
    $ben = managerBen();
    giveRole($ben, [Permission::ViewTimeOff->value => Scope::Reports]);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara']);
    $dan = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Dan']);
    TimeOff::factory()->for($cara)->approved($ben)->create(['first_date' => '2026-12-22', 'last_date' => '2026-12-24']);
    TimeOff::factory()->for($dan)->unpaid()->create(['first_date' => '2026-12-29', 'last_date' => '2027-01-04']);

    actingAs($ben);

    visit(route('home'))
        ->click('internal:role=link[name="Time Off"s] >> nth=1')
        ->assertPathIs(route('time-off-list.index', absolute: false))
        ->assertSee('Unpaid')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Approved"s]')
        ->assertQueryStringHas('state', 'approved')
        ->assertDontSee('Unpaid')
        ->assertSee('Dec 22, 2026')
        ->click('internal:role=link[name="Cara"s]')
        ->assertPathIs("/employees/{$cara->id}")
        ->assertQueryStringHas('tab', 'time-off')
        ->assertNoJavaScriptErrors();
});
