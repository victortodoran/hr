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

it('requests Time Off in a sheet over the Time Off page, previewing the Paid Days it uses, and lists it as Pending', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create(['name' => 'Dana']))->create(['name' => 'Cara', 'paid_days' => 20]);

    actingAs($cara);

    $page = visit(route('time-off.index'))
        ->assertSee('Dana decides each request.')
        ->assertSee('Paid Days left in 2026')
        ->click('internal:role=link[name="Request Time Off"s]')
        ->assertPathIs('/time-off/create')
        ->assertSee('2026: 20 Paid Days left.');

    pickDateRange($page, '#time-off-dates', '2026-12-14', '2026-12-16')
        ->assertSeeIn('[data-test="date-range-count"]', '3 weekdays')
        ->assertSee('2026: uses 3 Paid Days, leaving 17 Paid Days.')
        ->click('internal:role=button[name="Unpaid"s]')
        ->assertSee('Unpaid Time Off uses no Paid Days.')
        ->click('internal:role=button[name="Paid"s]')
        ->assertSee('2026: uses 3 Paid Days, leaving 17 Paid Days.')
        ->click('internal:role=dialog >> internal:role=button[name="Request Time Off"s]')
        ->assertSee('Time Off requested.')
        ->assertPathIs('/time-off')
        ->assertNotPresent('internal:role=dialog')
        ->assertSee('Pending')
        ->assertNoJavaScriptErrors();

    $timeOff = TimeOff::query()->sole();
    expect($timeOff->first_date->toDateString())->toBe('2026-12-14');
    expect($timeOff->last_date->toDateString())->toBe('2026-12-16');
    expect($timeOff->is_paid)->toBeTrue();
    expect($timeOff->state)->toBe(TimeOffState::Pending);
});

it('closes the sheet without requesting anything when cancelled', function () {
    actingAs(Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['paid_days' => 20]));

    pickDateRange(visit(route('time-off.create')), '#time-off-dates', '2026-12-14', '2026-12-16')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->assertPathIs('/time-off')
        ->assertNotPresent('internal:role=dialog')
        ->assertSee('Paid Days left in 2026')
        ->assertNoJavaScriptErrors();

    expect(TimeOff::query()->count())->toBe(0);
});

it('cancels Time Off from its row\'s menu after asking, keeping it on record as Cancelled', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'paid_days' => 20]);
    $timeOff = TimeOff::factory()->for($cara)->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($cara);

    visit(route('time-off.index'))
        ->click('internal:role=button[name="More for your Time Off"i]')
        ->click('internal:role=menuitem[name="Cancel…"s]')
        ->assertSee('Cancel Time Off')
        ->assertSee('stays on record as Cancelled, and its 3 Paid Days come back to you.')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel Time Off"s]')
        ->assertSee('Time Off cancelled.')
        ->assertSee('Cancelled')
        ->assertNoJavaScriptErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Cancelled);
});

it('requests Time Off on a phone from the bottom sheet\'s Request Time Off button', function () {
    actingAs(Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['paid_days' => 20]));

    $page = visit(route('time-off.index'))
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=link[name="Request Time Off"s]')
        ->assertPathIs('/time-off/create');

    pickDateRange($page, '#time-off-dates', '2026-12-14', '2026-12-14')
        ->assertSee('2026: uses 1 Paid Day, leaving 19 Paid Days.')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="Request Time Off"s]')
        ->assertSee('Time Off requested.')
        ->assertPathIs('/time-off')
        ->assertSee('Pending')
        ->assertNoJavaScriptErrors();

    expect(TimeOff::query()->sole()->state)->toBe(TimeOffState::Pending);
});
