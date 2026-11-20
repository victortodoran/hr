<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Http\Controllers\Travel\TravelExpenseApprovalController;
use App\Http\Controllers\Travel\TravelRequestDeclineController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelRequestDeclineController::class, TravelExpenseApprovalController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
});

/**
 * Ben, who manages Cara and may view her travel, so he still sees her
 * Travel Requests once he has decided them.
 *
 * @return array{Employee, Employee}
 */
function benWhoSeesCarasTravel(): array
{
    $ben = managerBen();
    giveRole($ben, [Permission::ViewTravel->value => Scope::Reports]);

    return [$ben, Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara'])];
}

it('lets the Manager decline a Pending Travel Request on its page with a reason, staying on it', function () {
    [$ben, $cara] = benWhoSeesCarasTravel();
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->create(['destination' => 'Berlin', 'first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($ben);

    visit(route('travel-requests.show', $berlin))
        ->assertSee('Approve')
        ->click('Decline…')
        ->assertSee('Decline this Travel Request?')
        ->fill('#decline-reason', 'The release is that week.')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Travel Request declined.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertSee('Why it was declined')
        ->assertDontSee('Decline…')
        ->assertNoJavaScriptErrors();

    expect($berlin->refresh()->state)->toBe(TravelRequestState::Declined);
});

it('lets the Manager approve a Pending Travel Expense on its Travel Request\'s page, staying on it', function () {
    [$ben, $cara] = benWhoSeesCarasTravel();
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $hotel = TravelExpense::factory()->for($berlin)->create(['description' => 'Hotel']);

    actingAs($ben);

    visit(route('travel-requests.show', $berlin))
        ->assertSee('Hotel')
        ->click('Approve')
        ->assertSee('1 Travel Expense approved.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertDontSee('Decline…')
        ->assertNoJavaScriptErrors();

    expect($hotel->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('shows the Employee no way to decide their own Pending Travel Request', function () {
    [, $cara] = benWhoSeesCarasTravel();
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->create(['destination' => 'Berlin', 'first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($cara);

    visit(route('travel-requests.show', $berlin))
        ->assertSee('Berlin')
        ->assertDontSee('Approve')
        ->assertDontSee('Decline…')
        ->assertNoJavaScriptErrors();
});

it('leads the Manager from a Travel Request\'s breadcrumbs back to the Travel Requests page', function () {
    [$ben, $cara] = benWhoSeesCarasTravel();
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->create(['destination' => 'Berlin', 'first_date' => '2026-12-14', 'last_date' => '2026-12-16']);

    actingAs($ben);

    visit(route('travel-requests.show', $berlin))
        ->assertSeeIn('[data-slot="page-header"] h1', 'Cara · Berlin')
        ->click('internal:role=link[name="Travel Requests"s]')
        ->assertPathIs('/travel-requests/all')
        ->assertNoJavaScriptErrors();
});
