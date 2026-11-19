<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOff\TimeOffCancellationController;
use App\Http\Controllers\TimeOff\TimeOffController;
use App\Http\Controllers\TimeOff\TimeOffPreviewController;
use App\Models\Employee;
use App\Models\TimeOff;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffController::class, TimeOffPreviewController::class, TimeOffCancellationController::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

it('requests Time Off on its own page, previewing the Paid Days it uses, and returns to Time Off with a toast', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara', 'paid_days' => 20]);

    actingAs($cara);

    visit(route('time-off.index'))
        ->click('Request Time Off')
        ->assertPathIs('/time-off/create')
        ->fill('#time-off-first-date', '2026-12-14')
        ->fill('#time-off-last-date', '2026-12-16')
        ->assertSee('2026: uses 3 Paid Days, leaving 17 Paid Days.')
        ->click('Request')
        ->assertSee('Time Off requested.')
        ->assertPathIs('/time-off')
        ->assertNoJavaScriptErrors();

    $timeOff = TimeOff::query()->sole();
    expect($timeOff->first_date->toDateString())->toBe('2026-12-14');
    expect($timeOff->last_date->toDateString())->toBe('2026-12-16');
    expect($timeOff->state)->toBe(TimeOffState::Pending);
});

it('returns to Time Off without requesting anything when cancelled', function () {
    actingAs(Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['paid_days' => 20]));

    visit(route('time-off.create'))
        ->fill('#time-off-first-date', '2026-12-14')
        ->click('Cancel')
        ->assertPathIs('/time-off')
        ->assertSee('Paid Days left in 2026')
        ->assertNoJavaScriptErrors();

    expect(TimeOff::query()->count())->toBe(0);
});

it('cancels Time Off after asking in a dialog, keeping it on record as Cancelled', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'paid_days' => 20]);
    $timeOff = TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($cara);

    visit(route('time-off.index'))
        ->click('internal:role=button[name="Cancel"s]')
        ->assertSee('Cancel your Time Off for Dec 14, 2026 – Dec 16, 2026?')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel Time Off"s]')
        ->assertSee('Time Off cancelled.')
        ->assertSee('Cancelled')
        ->assertNoJavaScriptErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Cancelled);
});
