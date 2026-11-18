<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Controllers\Travel\TravelExpenseReopeningController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Models\Builders\EmployeeBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\TravelExpensePolicy;
use App\Support\TravelHistory;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseReopeningController::class, TravelRequestController::class, TravelHistory::class, TravelExpensePolicy::class, TravelExpenseReceiptController::class, EmployeeBuilder::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

/**
 * Rae, who may reopen the Travel Expenses of her Reports (or the given
 * grants instead), below Ada at the top.
 *
 * @param  array<string, Scope|null>|null  $grants
 */
function travelReopener(?array $grants = null): Employee
{
    $ada = Employee::factory()->create(['name' => 'Ada']);

    return employeeWith($grants ?? [Permission::ReopenTravelExpenses->value => Scope::Reports], ['name' => 'Rae', 'manager_id' => $ada->id]);
}

/**
 * A Travel Expense paid on 4 Dec 2026 on an Approved trip of the
 * Employee's, Approved unless given another factory state, its receipt
 * stored on the disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function reopenableExpenseOf(Employee $employee, string $state = 'approved', array $attributes = []): TravelExpense
{
    $trip = TravelRequest::factory()->on(assignmentOf($employee))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $path = TravelExpense::RECEIPT_FOLDER.'/'.fake()->uuid().'.pdf';
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($path, 'receipt');
    $factory = TravelExpense::factory()->for($trip);

    return ($state === 'pending' ? $factory : $factory->{$state}())->create(['paid_on' => '2026-12-04', 'receipt_path' => $path, ...$attributes]);
}

it('offers to reopen the Approved expenses on a Travel Request\'s page to someone whose reopen Scope covers its Employee', function () {
    $rae = travelReopener([Permission::ReopenTravelExpenses->value => Scope::Everyone]);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $approved = reopenableExpenseOf($cara);
    $pending = TravelExpense::factory()->for($approved->travelRequest)->create(['paid_on' => '2026-12-05']);

    actingAs($rae)->get(route('travel-requests.show', $approved->travel_request_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Show')
            ->where('travelRequest.expenses.items.0.id', $approved->id)
            ->where('travelRequest.expenses.items.0.can_reopen', true)
            ->where('travelRequest.expenses.items.1.id', $pending->id)
            ->where('travelRequest.expenses.items.1.can_reopen', false));
});

it('never offers to reopen one\'s own expenses, nor those of an Employee the reopen Scope doesn\'t cover', function () {
    $rae = travelReopener();
    $own = reopenableExpenseOf($rae);
    $outsider = reopenableExpenseOf(Employee::factory()->create());

    actingAs($rae)->get(route('travel-requests.show', $own->travel_request_id))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequest.expenses.items.0.can_reopen', false));
    $reopenerOfReports = employeeWith([Permission::ReopenTravelExpenses->value => Scope::Reports, Permission::ViewTravel->value => Scope::Everyone]);
    actingAs($reopenerOfReports)->get(route('travel-requests.show', $outsider->travel_request_id))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequest.expenses.items.0.can_reopen', false));
});

it('lets Rae reopen her Report\'s Approved expense, withdrawing its approval, and skips a non-Report\'s and her own', function () {
    $rae = travelReopener();
    $report = reopenableExpenseOf(Employee::factory()->reportingTo($rae)->create());
    $outsider = reopenableExpenseOf(Employee::factory()->create());
    $own = reopenableExpenseOf($rae);

    actingAs($rae)->from(route('travel-requests.show', $report->travel_request_id))
        ->post(route('travel-requests.expenses.reopenings.store', $report->travel_request_id), ['ids' => [$report->id]])
        ->assertRedirect(route('travel-requests.show', $report->travel_request_id))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense reopened.']);
    foreach ([$outsider, $own] as $skipped) {
        actingAs($rae)->post(route('travel-requests.expenses.reopenings.store', $skipped->travel_request_id), ['ids' => [$skipped->id]])
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Travel Expenses reopened, 1 skipped.']);
    }

    $report->refresh();
    expect($report->state)->toBe(TravelExpenseState::Pending);
    expect($report->approved_by)->toBeNull();
    expect($report->approved_at)->toBeNull();
    expect($outsider->refresh()->state)->toBe(TravelExpenseState::Approved);
    expect($own->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('reopens only expenses of the Travel Request it is sent for', function () {
    $rae = travelReopener();
    $cara = Employee::factory()->reportingTo($rae)->create();
    $berlin = reopenableExpenseOf($cara);
    $paris = TravelExpense::factory()->for(TravelRequest::factory()->on(assignmentOf($cara, 'Atlas'))->approved()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-15']))
        ->approved()->create(['paid_on' => '2026-12-09']);

    actingAs($rae)->post(route('travel-requests.expenses.reopenings.store', $berlin->travel_request_id), ['ids' => [$berlin->id, $paris->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense reopened, 1 skipped.']);

    expect($berlin->refresh()->state)->toBe(TravelExpenseState::Pending);
    expect($paris->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('skips expenses that aren\'t Approved', function (string $state) {
    $rae = travelReopener();
    $expense = reopenableExpenseOf(Employee::factory()->reportingTo($rae)->create(), $state);

    actingAs($rae)->post(route('travel-requests.expenses.reopenings.store', $expense->travel_request_id), ['ids' => [$expense->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Travel Expenses reopened, 1 skipped.']);

    expect($expense->refresh()->state->value)->toBe($state);
})->with(['pending', 'declined']);

it('refuses reopening to a Manager without the Permission, even for their Report', function () {
    $ben = managerBen();
    $expense = reopenableExpenseOf(Employee::factory()->reportingTo($ben)->create());

    actingAs($ben)->post(route('travel-requests.expenses.reopenings.store', $expense->travel_request_id), ['ids' => [$expense->id]])->assertForbidden();

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('lets a reopen holder open the receipts of their Reports\' expenses, but not of anyone else', function () {
    $rae = travelReopener();
    $report = reopenableExpenseOf(Employee::factory()->reportingTo($rae)->create());
    $outsider = reopenableExpenseOf(Employee::factory()->create());

    actingAs($rae)->get(route('travel-expenses.receipt.show', $report))->assertOk();
    actingAs($rae)->get(route('travel-expenses.receipt.show', $outsider))->assertForbidden();
});

it('lets an expense of an Employee with no Manager be reopened for them to correct, which approves it again', function () {
    $rae = travelReopener([Permission::ReopenTravelExpenses->value => Scope::Everyone]);
    $ada = Employee::query()->findOrFail($rae->manager_id);
    $approved = reopenableExpenseOf($ada, attributes: ['approved_by' => null, 'approved_at' => '2026-12-04 10:00']);

    actingAs($rae)->get(route('travel-requests.show', $approved->travel_request_id))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequest.expenses.items.0.can_reopen', true));
    actingAs($rae)->post(route('travel-requests.expenses.reopenings.store', $approved->travel_request_id), ['ids' => [$approved->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense reopened.']);
    expect($approved->refresh()->state)->toBe(TravelExpenseState::Pending);

    actingAs($ada)->put(route('travel-expenses.update', $approved), [
        'paid_on' => '2026-12-04', 'amount' => '25', 'category' => 'meals', 'description' => 'Lunch, corrected',
    ])->assertRedirect(route('travel-requests.show', $approved->travel_request_id));

    expect($approved->refresh()->state)->toBe(TravelExpenseState::Approved);
});
