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

it('requests travel in a sheet over the Travel page, its only Assignment chosen and every day counted, then opens it from Upcoming and cancels it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    $page = visit(route('travel-requests.index'))
        ->assertSee("You haven't requested any yet.")
        ->click('internal:role=link[name="Request Travel"s]')
        ->assertPathIs('/travel-requests/create')
        ->assertSee('Runs')
        ->fill('#travel-destination', 'Berlin');

    pickDateRange($page, '#travel-dates', '2026-12-03', '2026-12-05')
        ->assertSeeIn('[data-test="date-range-count"]', '3 days')
        ->fill('#travel-purpose', 'Relaunch workshop')
        ->click('internal:role=dialog >> internal:role=button[name="Request Travel"s]')
        ->assertSee('Travel Request made.')
        ->assertPathIs('/travel-requests')
        ->assertNotPresent('internal:role=dialog')
        ->assertSeeIn('[data-slot="group-row"]', 'Upcoming')
        ->click('internal:role=link[name="Berlin"s]')
        ->assertSee('Relaunch workshop')
        ->assertSeeLink('Travel')
        ->click('internal:role=button[name="More for this Travel Request"s]')
        ->click('internal:role=menuitem[name="Cancel Travel Request…"s]')
        ->assertSee('It stays on record as Cancelled')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel Travel Request"s]')
        ->assertSee('Travel Request cancelled.')
        ->assertNotPresent('internal:role=dialog')
        ->assertNoJavaScriptErrors();

    $travelRequest = TravelRequest::query()->sole();
    expect($travelRequest->destination)->toBe('Berlin');
    expect($travelRequest->first_date->toDateString())->toBe('2026-12-03');
    expect($travelRequest->last_date->toDateString())->toBe('2026-12-05');
    expect($travelRequest->state)->toBe(TravelRequestState::Cancelled);
});

it('closes the Request Travel sheet without making a Travel Request when cancelled', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    visit(route('travel-requests.create'))
        ->fill('#travel-destination', 'Berlin')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->assertPathIs('/travel-requests')
        ->assertNotPresent('internal:role=dialog')
        ->assertNoJavaScriptErrors();

    expect(TravelRequest::query()->exists())->toBeFalse();
});

it('keeps the Request Travel sheet from being sent while no Assignment is chosen', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    assignmentOf($cara);
    assignmentOf($cara, 'Atlas');

    actingAs($cara);

    $page = visit(route('travel-requests.create'))
        ->fill('#travel-destination', 'Berlin');

    $page = pickDateRange($page, '#travel-dates', '2026-12-03', '2026-12-05')
        ->fill('#travel-purpose', 'Relaunch workshop')
        ->click('internal:role=dialog >> internal:role=button[name="Request Travel"s]')
        ->assertPathIs('/travel-requests/create')
        ->assertNoJavaScriptErrors();

    $hiddenSelect = 'document.querySelector(\'select[name="assignment_id"]\')';
    expect($page->script("{$hiddenSelect}.validity.valueMissing"))->toBeTrue();

    // The browser's message points at the hidden select, so it must sit beside the dropdown, not at the top of the page.
    $pixelsFromDropdown = $page->script("Math.abs({$hiddenSelect}.getBoundingClientRect().top - document.getElementById('travel-assignment').getBoundingClientRect().top)");
    expect($pixelsFromDropdown)->toBeLessThan(50);
    expect(TravelRequest::query()->exists())->toBeFalse();
});

it('requests travel from the bottom sheet on a phone without scrolling sideways', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    $page = visit(route('travel-requests.index'))
        ->resize(390, 844)
        ->click('internal:role=link[name="Request Travel"s]')
        ->assertPathIs('/travel-requests/create')
        ->fill('#travel-destination', 'Berlin');

    pickDateRange($page, '#travel-dates', '2026-12-03', '2026-12-05')
        ->fill('#travel-purpose', 'Relaunch workshop')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="Request Travel"s]')
        ->assertSee('Travel Request made.')
        ->assertSee('Berlin')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect(TravelRequest::query()->sole()->destination)->toBe('Berlin');
});

it('stacks each Travel Request row on a phone without scrolling sideways', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);

    actingAs($cara);

    visit(route('travel-requests.index'))
        ->resize(390, 844)
        ->assertSee('Berlin')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
