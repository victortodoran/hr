<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\TimeOffCancellationController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Policies\TimeOffPolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffCancellationController::class, TimeOffPolicy::class, TimeOff::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

/**
 * Cara's Time Off from Mon 14 to Wed 16 Dec 2026, Pending unless given
 * another factory state.
 */
function carasTimeOff(?string $state = null): TimeOff
{
    $factory = TimeOff::factory()->for(Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'paid_days' => 20]));

    return ($state === null ? $factory : $factory->{$state}())->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);
}

it('lets an Employee cancel their Pending or Approved Time Off before its first date, keeping it on record', function (?string $state) {
    $timeOff = carasTimeOff($state);
    travelTo('2026-12-13 23:00');

    actingAs($timeOff->employee)->post(route('time-off.cancellation.store', $timeOff))
        ->assertRedirect(route('time-off.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Time Off cancelled.']);

    $timeOff->refresh();
    expect($timeOff->state)->toBe(TimeOffState::Cancelled);
    expect($timeOff->cancelled_at)->toEqual(now());
    expect($timeOff->first_date->toDateString())->toBe('2026-12-14');
})->with(['pending' => null, 'approved' => 'approved']);

it('frees the Paid Days of cancelled Time Off', function () {
    $timeOff = carasTimeOff();

    actingAs($timeOff->employee)->post(route('time-off.cancellation.store', $timeOff));

    actingAs($timeOff->employee)->get(route('time-off.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paidDaysLeft', 20)
            ->where('timeOff.0.can_cancel', false));
});

it('refuses cancelling Time Off from its first date on', function (string $today) {
    $timeOff = carasTimeOff('approved');
    travelTo($today);

    actingAs($timeOff->employee)->post(route('time-off.cancellation.store', $timeOff))->assertForbidden();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
})->with(['on the first date' => '2026-12-14 00:00', 'once over' => '2026-12-17 09:00']);

it('refuses cancelling Declined or already Cancelled Time Off', function (string $state) {
    $timeOff = carasTimeOff($state);

    actingAs($timeOff->employee)->post(route('time-off.cancellation.store', $timeOff))->assertForbidden();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::from($state));
})->with(['declined', 'cancelled']);

it('refuses cancelling someone else\'s Time Off, even as an Employee with no Manager', function () {
    $timeOff = carasTimeOff();

    actingAs(Employee::factory()->create())->post(route('time-off.cancellation.store', $timeOff))->assertForbidden();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Pending);
});
