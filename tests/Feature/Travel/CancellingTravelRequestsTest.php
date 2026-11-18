<?php

use App\Enums\TravelRequestState;
use App\Http\Controllers\Travel\TravelRequestCancellationController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Policies\TravelRequestPolicy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelRequestCancellationController::class, TravelRequestPolicy::class, TravelRequest::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
    CompanySettings::factory()->create();
});

/**
 * Cara's Travel Request to Berlin from Thu 3 to Sat 5 Dec 2026 on Relaunch,
 * Pending unless given another factory state.
 */
function carasTravelRequest(?string $state = null): TravelRequest
{
    $factory = TravelRequest::factory()->on(assignmentOf(Employee::factory()->startedLongAgo()->create(['name' => 'Cara'])));

    return ($state === null ? $factory : $factory->{$state}())->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
}

it('lets an Employee cancel their Pending or Approved Travel Request before its first date, keeping it on record', function (?string $state) {
    $travelRequest = carasTravelRequest($state);
    travelTo('2026-12-02 23:00');

    actingAs($travelRequest->employee)->post(route('travel-requests.cancellation.store', $travelRequest))
        ->assertRedirect(route('travel-requests.show', $travelRequest))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Request cancelled.']);

    $travelRequest->refresh();
    expect($travelRequest->state)->toBe(TravelRequestState::Cancelled);
    expect($travelRequest->cancelled_at)->toEqual(now());
    expect($travelRequest->first_date->toDateString())->toBe('2026-12-03');
})->with(['pending' => null, 'approved' => 'approved']);

it('refuses cancelling a Travel Request from its first date on', function (string $today) {
    $travelRequest = carasTravelRequest('approved');
    travelTo($today);

    actingAs($travelRequest->employee)->post(route('travel-requests.cancellation.store', $travelRequest))->assertForbidden();

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::Approved);
})->with(['on the first date' => '2026-12-03 00:00', 'once over' => '2026-12-06 09:00']);

it('refuses cancelling a Declined or already Cancelled Travel Request', function (string $state) {
    $travelRequest = carasTravelRequest($state);

    actingAs($travelRequest->employee)->post(route('travel-requests.cancellation.store', $travelRequest))->assertForbidden();

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::from($state));
})->with(['declined', 'cancelled']);

it('refuses cancelling someone else\'s Travel Request, even as an Employee with no Manager', function () {
    $travelRequest = carasTravelRequest();

    actingAs(Employee::factory()->create())->post(route('travel-requests.cancellation.store', $travelRequest))->assertForbidden();

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::Pending);
});
