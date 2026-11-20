<?php

use App\Enums\TimeOffState;
use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Enums\WorkEntryState;
use App\Http\Controllers\TimeOff\TimeOffApprovalController;
use App\Http\Controllers\Travel\TravelExpenseApprovalController;
use App\Http\Controllers\Travel\TravelExpenseDeclineController;
use App\Http\Controllers\Travel\TravelRequestDeclineController;
use App\Http\Controllers\WorkEntries\ApprovalController;
use App\Http\Controllers\WorkEntries\WorkEntryDeclineController;
use App\Models\CompanySettings;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, WorkEntryDeclineController::class, TimeOffApprovalController::class, TravelRequestDeclineController::class, TravelExpenseApprovalController::class, TravelExpenseDeclineController::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

it('lets a Manager work through one Employee\'s Work Entries on one Project at a time, switch tabs, kept in the URL, and approve Time Off from its tab', function () {
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id]);
    WorkEntry::factory()->for(assignmentOf($cara))->create(['date' => '2026-11-30', 'description' => 'Reviewing the API']);
    WorkEntry::factory()->for(assignmentOf($cara, 'Website'))->create(['date' => '2026-11-30', 'description' => 'Fixing the footer']);
    $timeOff = TimeOff::factory()->for($cara)->unpaid()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($ben);

    $page = visit(route('approvals.index'))
        ->assertSee('Cara (2)')
        ->assertDontSee('Any Employee')
        ->assertSee('Relaunch (1)')
        ->assertDontSee('Any Project')
        ->assertSee('Reviewing the API')
        ->assertDontSee('Fixing the footer')
        ->click('internal:role=tab[name="Time Off"i]')
        ->assertQueryStringHas('tab', 'time-off')
        ->assertSee('Cara')
        ->click('main [data-slot="data-row"] >> nth=0 >> [aria-label^="Approve "]')
        ->assertSee('Time Off approved.')
        ->assertQueryStringHas('tab', 'time-off')
        ->click('internal:role=tab[name="Work Entries"i]')
        ->assertQueryStringHas('tab', 'work-entries')
        ->assertSee('Reviewing the API')
        ->assertNoJavaScriptErrors();

    expect($timeOff->refresh()->state)->toBe(TimeOffState::Approved);
});

it('keeps Work Entries ticked on one page when moving to the next, approving them all together', function () {
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara);
    collect(range(0, 25))->each(fn (int $day) => WorkEntry::factory()->for($relaunch)->create(['date' => CarbonImmutable::parse('2026-11-02')->addDays($day)]));

    actingAs($ben);

    visit(route('approvals.index', ['tab' => 'work-entries']))
        ->click('main [data-slot="data-row"] >> nth=0 >> [role="checkbox"]')
        ->assertSee('1 selected')
        ->click('Next')
        ->assertSee('26–26 of 26')
        ->click('main [data-slot="data-row"] >> nth=0 >> [role="checkbox"]')
        ->assertSee('2 selected')
        ->click('internal:role=button[name="Approve 2 Work Entries"s]')
        ->assertSee('2 Work Entries approved.')
        ->assertNotPresent('[data-slot="selection-bar"]')
        ->assertSee('24 of 24 shown')
        ->assertDontSee('None are waiting')
        ->assertNoJavaScriptErrors();

    expect(WorkEntry::query()->where('state', WorkEntryState::Approved)->count())->toBe(2);
});

it('lets a Manager decline a Travel Request from its tab with a reason', function () {
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id, 'name' => 'Cara']);
    $travelRequest = TravelRequest::factory()->on(assignmentOf($cara))->create(['destination' => 'Berlin', 'first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'purpose' => 'Relaunch go-live']);

    actingAs($ben);

    visit(route('approvals.index'))
        ->click('internal:role=tab[name="Travel Requests"i]')
        ->assertQueryStringHas('tab', 'travel-requests')
        ->assertSee('Berlin')
        ->assertSee('Relaunch go-live')
        ->click('main [data-slot="data-row"] >> nth=0 >> [aria-label^="Decline "]')
        ->assertSee('Decline Travel Request')
        ->fill('#decline-reason', 'The release is that week.')
        ->click('internal:role=dialog >> internal:role=button[name="Decline Travel Request"s]')
        ->assertSee('Travel Request declined.')
        ->assertQueryStringHas('tab', 'travel-requests')
        ->assertSee('None is waiting')
        ->assertNoJavaScriptErrors();

    expect($travelRequest->refresh()->state)->toBe(TravelRequestState::Declined);
});

it('lets a Manager approve two Travel Expenses together and decline another with a reason from their tab', function () {
    CompanySettings::factory()->create(['currency' => 'RON']);
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id, 'name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-11-23', 'last_date' => '2026-11-25']);
    $flight = TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-11-20', 'description' => 'Flight']);
    $hotel = TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-11-25', 'description' => 'Hotel']);
    $taxi = TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-11-26', 'description' => 'Taxi']);

    actingAs($ben);

    visit(route('approvals.index'))
        ->click('internal:role=tab[name="Travel Expenses"i]')
        ->assertQueryStringHas('tab', 'travel-expenses')
        ->assertSee('Berlin')
        ->assertSee('Flight')
        ->click('main [data-slot="data-row"] >> nth=0 >> [role="checkbox"]')
        ->click('main [data-slot="data-row"] >> nth=1 >> [role="checkbox"]')
        ->assertSee('2 selected')
        ->click('internal:role=button[name="Approve 2 Travel Expenses"s]')
        ->assertSee('2 Travel Expenses approved.')
        ->assertSee('Taxi')
        ->assertDontSee('Hotel')
        ->click('main [data-slot="data-row"] >> nth=0 >> [role="checkbox"]')
        ->click('[data-slot="selection-bar"] >> internal:role=button[name="Decline…"s]')
        ->fill('#decline-reason', 'The taxi isn\'t on the receipt.')
        ->click('internal:role=dialog >> internal:role=button[name="Decline Travel Expense"s]')
        ->assertSee('1 Travel Expense declined.')
        ->assertSee('None is waiting')
        ->assertNoJavaScriptErrors();

    expect($flight->refresh()->state)->toBe(TravelExpenseState::Approved);
    expect($hotel->refresh()->state)->toBe(TravelExpenseState::Approved);
    expect($taxi->refresh()->state)->toBe(TravelExpenseState::Declined);
});

it('lets a Manager approve one Work Entry and decline another from their rows, keeping the rest ticked, and fits a phone', function () {
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara);
    $review = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-10', 'description' => 'Reviewing the API']);
    $interviews = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-11', 'description' => 'Interviews']);
    $checkout = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-12', 'description' => 'Checkout API']);

    actingAs($ben);

    visit(route('approvals.index'))
        ->click("[aria-label=\"Select Cara's Work Entry on Nov 10, 2026\"]")
        ->click("[aria-label=\"Select Cara's Work Entry on Nov 12, 2026\"]")
        ->assertSee('2 selected')
        ->click("internal:role=button[name=\"Approve Cara's Work Entry on Nov 10, 2026\"s]")
        ->assertSee('1 Work Entry approved.')
        ->assertDontSee('Reviewing the API')
        ->assertSee('1 selected')
        ->click("internal:role=button[name=\"Decline Cara's Work Entry on Nov 11, 2026\"s]")
        ->assertSee('Decline Work Entry')
        ->fill('#decline-reason', 'Interviews go on Internal, not Acme')
        ->click('internal:role=dialog >> internal:role=button[name="Decline Work Entry"s]')
        ->assertSee('1 Work Entry declined.')
        ->assertDontSee('Interviews')
        ->assertSee('Checkout API')
        ->assertSee('1 selected')
        ->assertSee('1 of 1 shown')
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($review->refresh()->state)->toBe(WorkEntryState::Approved)
        ->and($interviews->refresh()->state)->toBe(WorkEntryState::Declined)
        ->and($interviews->decline_reason)->toBe('Interviews go on Internal, not Acme')
        ->and($checkout->refresh()->state)->toBe(WorkEntryState::Pending);
});

it('lets a Manager approve one Travel Expense from its row, and on a phone decline another from its sheet', function () {
    CompanySettings::factory()->create(['currency' => 'RON']);
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id, 'name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-11-23', 'last_date' => '2026-11-25']);
    $flight = TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-11-20', 'description' => 'Flight']);
    $taxi = TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-11-26', 'description' => 'Taxi']);

    actingAs($ben);

    visit(route('approvals.index', ['tab' => 'travel-expenses']))
        ->click('main [data-slot="data-row"] >> nth=0 >> [aria-label^="Approve "]')
        ->assertSee('1 Travel Expense approved.')
        ->assertDontSee('Flight')
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('main [data-slot="data-row"] >> nth=0')
        ->assertQueryStringHas('record', (string) $taxi->id)
        ->click('internal:role=dialog >> internal:role=button[name="Decline…"s]')
        ->assertSee('Decline Travel Expense')
        ->fill('#decline-reason', 'The taxi isn\'t on the receipt.')
        ->click('internal:role=dialog >> internal:role=button[name="Decline Travel Expense"s]')
        ->assertSee('1 Travel Expense declined.')
        ->assertSee('None is waiting')
        ->assertNoJavaScriptErrors();

    expect($flight->refresh()->state)->toBe(TravelExpenseState::Approved)
        ->and($taxi->refresh()->state)->toBe(TravelExpenseState::Declined)
        ->and($taxi->decline_reason)->toBe('The taxi isn\'t on the receipt.');
});

it('lets a Manager on a phone tick a whole week of Work Entries and approve it from the bar at the bottom', function () {
    $ben = managerBen();
    $cara = recorder(['manager_id' => $ben->id]);
    $relaunch = assignmentOf($cara);
    $week = collect(['2026-11-09', '2026-11-10', '2026-11-11'])->map(fn (string $date) => WorkEntry::factory()->for($relaunch)->create(['date' => $date]));
    $nextWeek = WorkEntry::factory()->for($relaunch)->create(['date' => '2026-11-16']);

    actingAs($ben);

    visit(route('approvals.index'))
        ->resize(390, 844)
        ->click('main [data-slot="group-row"] >> nth=0 >> [role="checkbox"]')
        ->assertSee('3 selected')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[data-slot="selection-bar"] >> internal:role=button[name="Approve 3"s]')
        ->assertSee('3 Work Entries approved.')
        ->assertNotPresent('[data-slot="selection-bar"]')
        ->assertSee('1 of 1 shown')
        ->assertNoJavaScriptErrors();

    expect($week->every(fn (WorkEntry $entry) => $entry->refresh()->state === WorkEntryState::Approved))->toBeTrue()
        ->and($nextWeek->refresh()->state)->toBe(WorkEntryState::Pending);
});
