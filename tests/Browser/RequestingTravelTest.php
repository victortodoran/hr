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

it('requests travel on an Assignment from its own page, counting every day, then opens it from Upcoming and cancels it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    visit(route('travel-requests.index'))
        ->assertSee('You have none coming up.')
        ->click('Request travel')
        ->assertPathIs('/travel-requests/create')
        ->click('#travel-assignment')
        ->click('[role="option"] >> text=Relaunch')
        ->assertSee('Runs')
        ->fill('#travel-destination', 'Berlin')
        ->fill('#travel-first-date', '2026-12-03')
        ->fill('#travel-last-date', '2026-12-05')
        ->fill('#travel-purpose', 'Relaunch workshop')
        ->assertSee('3 days')
        ->click('Request')
        ->assertSee('Travel Request made.')
        ->assertPathIs('/travel-requests')
        ->assertSeeIn('section:has(h2:text-is("Upcoming"))', 'Berlin')
        ->click('Berlin')
        ->assertSee('Relaunch workshop')
        ->assertSeeLink('Travel')
        ->click('Cancel travel')
        ->assertSee('Cancel this Travel Request?')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel travel"s]')
        ->assertSee('Travel Request cancelled.')
        ->assertDontSee('Cancel this Travel Request?')
        ->assertNoJavaScriptErrors();

    $travelRequest = TravelRequest::query()->sole();
    expect($travelRequest->destination)->toBe('Berlin');
    expect($travelRequest->first_date->toDateString())->toBe('2026-12-03');
    expect($travelRequest->state)->toBe(TravelRequestState::Cancelled);
});

it('leaves the request travel page for the Travel list without making a Travel Request', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    visit(route('travel-requests.create'))
        ->click('Cancel')
        ->assertPathIs('/travel-requests')
        ->assertNoJavaScriptErrors();

    expect(TravelRequest::query()->exists())->toBeFalse();
});

it('keeps the request travel form from being sent while no Assignment is chosen', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara']);
    assignmentOf($cara);

    actingAs($cara);

    $page = visit(route('travel-requests.create'))
        ->fill('#travel-destination', 'Berlin')
        ->fill('#travel-first-date', '2026-12-03')
        ->fill('#travel-last-date', '2026-12-05')
        ->fill('#travel-purpose', 'Relaunch workshop')
        ->click('Request')
        ->assertPathIs('/travel-requests/create')
        ->assertNoJavaScriptErrors();

    $hiddenSelect = 'document.querySelector(\'select[name="assignment_id"]\')';
    expect($page->script("{$hiddenSelect}.validity.valueMissing"))->toBeTrue();

    // The browser's message points at the hidden select, so it must sit beside the dropdown, not at the top of the page.
    $pixelsFromDropdown = $page->script("Math.abs({$hiddenSelect}.getBoundingClientRect().top - document.getElementById('travel-assignment').getBoundingClientRect().top)");
    expect($pixelsFromDropdown)->toBeLessThan(50);
    expect(TravelRequest::query()->exists())->toBeFalse();
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
