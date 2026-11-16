<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOffController;
use App\Http\Controllers\TimeOffPreviewController;
use App\Models\Employee;
use App\Models\TimeOff;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffController::class, TimeOffPreviewController::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

it('previews the Paid Days chosen dates use and closes the dialog with a toast once requested', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara', 'paid_days' => 20]);

    actingAs($cara);

    visit(route('time-off.index'))
        ->click('Request Time Off')
        ->assertVisible('internal:role=dialog')
        ->fill('#time-off-first-date', '2026-12-14')
        ->fill('#time-off-last-date', '2026-12-16')
        ->assertSee('2026: uses 3 Paid Days, leaving 17 Paid Days.')
        ->click('Request')
        ->assertSee('Time Off requested.')
        ->assertMissing('internal:role=dialog')
        ->assertNoJavaScriptErrors();

    $timeOff = TimeOff::query()->sole();
    expect($timeOff->first_date->toDateString())->toBe('2026-12-14');
    expect($timeOff->last_date->toDateString())->toBe('2026-12-16');
    expect($timeOff->state)->toBe(TimeOffState::Pending);
});
