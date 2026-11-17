<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOff\TimeOffController;
use App\Http\Controllers\TimeOff\TimeOffPreviewController;
use App\Models\Employee;
use App\Models\TimeOff;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffController::class, TimeOffPreviewController::class);

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
