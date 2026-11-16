<?php

use App\Enums\TravelRequestState;
use App\Http\Controllers\Travel\TravelRequestCancellationController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelRequest;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelRequestController::class, TravelRequestCancellationController::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
    CompanySettings::factory()->create();
});

it('requests travel on an Assignment from the dialog, counting every day, then cancels it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    visit(route('travel-requests.index'))
        ->assertSee('You haven\'t made any yet.')
        ->click('Request travel')
        ->assertVisible('internal:role=dialog')
        ->click('#travel-assignment')
        ->click('[role="option"] >> text=Relaunch')
        ->fill('#travel-destination', 'Berlin')
        ->fill('#travel-first-date', '2026-12-03')
        ->fill('#travel-last-date', '2026-12-05')
        ->fill('#travel-purpose', 'Relaunch workshop')
        ->assertSee('3 days')
        ->click('Request')
        ->assertSee('Travel Request made.')
        ->assertMissing('internal:role=dialog')
        ->assertSee('Berlin')
        ->click('Cancel')
        ->assertSee('Travel Request cancelled.')
        ->assertNoJavaScriptErrors();

    $travelRequest = TravelRequest::query()->sole();
    expect($travelRequest->destination)->toBe('Berlin');
    expect($travelRequest->first_date->toDateString())->toBe('2026-12-03');
    expect($travelRequest->state)->toBe(TravelRequestState::Cancelled);
});
