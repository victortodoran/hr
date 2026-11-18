<?php

use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseApprovalController;
use App\Http\Controllers\Travel\TravelExpenseDeclineController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Controllers\WorkEntries\ApprovalController;
use App\Http\Requests\Travel\DeclineTravelExpensesRequest;
use App\Http\Requests\Travel\SelectedTravelExpensesRequest;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\TravelExpensePolicy;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(ApprovalController::class, TravelExpenseApprovalController::class, TravelExpenseDeclineController::class, SelectedTravelExpensesRequest::class, DeclineTravelExpensesRequest::class, TravelExpensePolicy::class, TravelExpenseBuilder::class, TravelExpenseReceiptController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

/**
 * Cara's Approved trip to Berlin from 3 to 5 Dec 2026, with the given
 * Manager.
 */
function carasBerlinTrip(Employee $manager): TravelRequest
{
    $cara = Employee::factory()->startedLongAgo()->reportingTo($manager)->create(['name' => 'Cara']);

    return TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
}

/**
 * A Travel Expense on the Travel Request, Pending unless given another
 * factory state, its receipt stored on the disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function expenseOn(TravelRequest $travelRequest, string $state = 'pending', array $attributes = []): TravelExpense
{
    $path = TravelExpense::RECEIPT_FOLDER.'/'.fake()->uuid().'.pdf';
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($path, 'receipt');
    $factory = TravelExpense::factory()->for($travelRequest);

    return ($state === 'pending' ? $factory : $factory->{$state}())->create(['receipt_path' => $path, ...$attributes]);
}

dataset('travel expense answers', [
    'approve' => ['approvals.travel-expenses.store', [], 'No Travel Expenses approved, 1 skipped.'],
    'decline' => ['approvals.travel-expenses.declines.store', ['decline_reason' => 'No receipt for this.'], 'No Travel Expenses declined, 1 skipped.'],
]);

it('lets Ben approve Cara\'s flight and hotel together and decline her taxi with a reason', function () {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $flight = expenseOn($berlin, attributes: ['category' => TravelExpenseCategory::Transport, 'description' => 'Flight']);
    $hotel = expenseOn($berlin, attributes: ['category' => TravelExpenseCategory::Accommodation, 'description' => 'Hotel']);
    $taxi = expenseOn($berlin, attributes: ['category' => TravelExpenseCategory::Transport, 'description' => 'Taxi']);

    actingAs($ben)->from(route('approvals.index', ['tab' => 'travel-expenses']))
        ->post(route('approvals.travel-expenses.store'), ['ids' => [$flight->id, $hotel->id]])
        ->assertRedirect(route('approvals.index', ['tab' => 'travel-expenses']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Travel Expenses approved.']);
    actingAs($ben)->post(route('approvals.travel-expenses.declines.store'), ['ids' => [$taxi->id], 'decline_reason' => 'The taxi isn\'t on the receipt.'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense declined.']);

    foreach ([$flight, $hotel] as $approved) {
        $approved->refresh();
        expect($approved->state)->toBe(TravelExpenseState::Approved);
        expect($approved->approved_by)->toBe($ben->id);
        expect($approved->approved_at)->toEqual(now());
        expect($approved->declined_by)->toBeNull();
    }
    $taxi->refresh();
    expect($taxi->state)->toBe(TravelExpenseState::Declined);
    expect($taxi->declined_by)->toBe($ben->id);
    expect($taxi->declined_at)->toEqual(now());
    expect($taxi->decline_reason)->toBe('The taxi isn\'t on the receipt.');
    expect($taxi->approved_by)->toBeNull();

    actingAs($berlin->employee)->get(route('travel-requests.show', $berlin))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequest.expenses.items.2.state', 'declined')
            ->where('travelRequest.expenses.items.2.decline_reason', 'The taxi isn\'t on the receipt.'));
});

it('refuses answering Cara\'s expenses to anyone but her Manager, even with the Admin Role', function (string $route, array $form) {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $expense = expenseOn($berlin);
    $admin = Employee::factory()->create(['manager_id' => $ben->manager_id]);
    $admin->roles()->attach(Role::factory()->admin()->create());
    Employee::factory()->reportingTo($admin)->create();
    $ada = Employee::query()->findOrFail($ben->manager_id);
    Employee::factory()->reportingTo($berlin->employee)->create();

    foreach ([$admin, $ada, $berlin->employee] as $outsider) {
        actingAs($outsider)->post(route($route), ['ids' => [$expense->id], ...$form]);
    }

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Pending);
})->with('travel expense answers');

it('refuses the Approvals actions to an Employee who manages nobody, even with the Admin Role', function (string $route, array $form) {
    $expense = expenseOn(carasBerlinTrip(managerBen()));
    $admin = Employee::factory()->create();
    $admin->roles()->attach(Role::factory()->admin()->create());

    actingAs($admin)->post(route($route), ['ids' => [$expense->id], ...$form])->assertForbidden();

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Pending);
})->with('travel expense answers');

it('skips an expense that isn\'t Pending, leaving it as it was', function (string $state, string $route, array $form, string $toast) {
    $ben = managerBen();
    $expense = expenseOn(carasBerlinTrip($ben), $state);

    actingAs($ben)->post(route($route), ['ids' => [$expense->id], ...$form])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $toast]);

    $after = $expense->fresh();
    expect($after?->state)->toBe($expense->state);
    expect($after?->approved_by)->toBe($expense->approved_by);
    expect($after?->declined_by)->toBe($expense->declined_by);
})->with(['approved', 'declined'])->with('travel expense answers');

it('approves only once when the same approval is sent twice', function () {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $ids = [expenseOn($berlin)->id, expenseOn($berlin)->id];

    actingAs($ben)->post(route('approvals.travel-expenses.store'), ['ids' => $ids])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Travel Expenses approved.']);
    $firstApproval = now()->toImmutable();
    travelTo('2026-12-10 09:05');
    actingAs($ben)->post(route('approvals.travel-expenses.store'), ['ids' => $ids])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Travel Expenses approved, 2 skipped.']);

    foreach (TravelExpense::query()->get() as $expense) {
        expect($expense->state)->toBe(TravelExpenseState::Approved);
        expect($expense->approved_at)->toEqual($firstApproval);
    }
});

it('says how many were skipped when only some of the selection can be approved', function () {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $pending = expenseOn($berlin);
    $approved = expenseOn($berlin, 'approved');

    actingAs($ben)->post(route('approvals.travel-expenses.store'), ['ids' => [$pending->id, $approved->id, $pending->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense approved, 1 skipped.']);
});

it('refuses a decline without a reason', function (mixed $reason) {
    $ben = managerBen();
    $expense = expenseOn(carasBerlinTrip($ben));

    actingAs($ben)->post(route('approvals.travel-expenses.declines.store'), ['ids' => [$expense->id], 'decline_reason' => $reason])
        ->assertSessionHasErrors('decline_reason');

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Pending);
})->with(['missing' => null, 'blank' => '   ', 'not text' => [['No receipt.']], 'over 1,000 characters' => str_repeat('a', 1001)]);

it('says to give a reason when declining without one', function () {
    $ben = managerBen();
    $expense = expenseOn(carasBerlinTrip($ben));

    actingAs($ben)->post(route('approvals.travel-expenses.declines.store'), ['ids' => [$expense->id]])
        ->assertSessionHasErrors(['decline_reason' => 'Give a reason for declining.']);
});

it('refuses an approval or decline with nothing selected', function (string $route, array $form, string $toast, mixed $ids) {
    $ben = managerBen();
    carasBerlinTrip($ben);

    actingAs($ben)->post(route($route), ['ids' => $ids, ...$form])
        ->assertSessionHasErrors(['ids' => 'Select at least one Travel Expense.']);
})->with('travel expense answers')->with(['none' => [[]], 'not a list' => [['a' => 1]]]);

it('refuses IDs that aren\'t whole numbers', function (string $route, array $form) {
    $ben = managerBen();
    carasBerlinTrip($ben);

    actingAs($ben)->post(route($route), ['ids' => ['flight'], ...$form])
        ->assertSessionHasErrors('ids.0');
})->with('travel expense answers');

it('lets the next Manager up answer once Cara\'s Manager has left, and Ben answer once Cara has left', function () {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $first = expenseOn($berlin);
    $second = expenseOn($berlin);
    $berlin->employee->update(['leaving_date' => '2026-12-09']);

    actingAs($ben)->post(route('approvals.travel-expenses.store'), ['ids' => [$first->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense approved.']);

    $ben->update(['leaving_date' => '2026-12-09']);
    $ada = Employee::query()->findOrFail($ben->manager_id);
    actingAs($ada)->post(route('approvals.travel-expenses.store'), ['ids' => [$second->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense approved.']);

    expect($first->refresh()->approved_by)->toBe($ben->id);
    expect($second->refresh()->approved_by)->toBe($ada->id);
});

it('lets Ben open the receipt of Cara\'s expense while he can answer it, and nobody else but Cara without a Permission that shows it', function () {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $expense = expenseOn($berlin);
    $colleague = Employee::factory()->startedLongAgo()->create();

    actingAs($ben)->get(route('travel-expenses.receipt.show', $expense))
        ->assertOk()
        ->assertStreamedContent('receipt');
    foreach ([$colleague, Employee::query()->findOrFail($ben->manager_id)] as $outsider) {
        actingAs($outsider)->get(route('travel-expenses.receipt.show', $expense))->assertForbidden();
    }

    actingAs($ben)->post(route('approvals.travel-expenses.store'), ['ids' => [$expense->id]]);

    actingAs($ben)->get(route('travel-expenses.receipt.show', $expense))->assertForbidden();
    actingAs($berlin->employee)->get(route('travel-expenses.receipt.show', $expense))->assertOk();
});

it('lists the Pending Travel Expenses of the Employees Ben effectively manages, grouped by Travel Request, earliest travel first', function () {
    $ben = managerBen();
    $berlin = carasBerlinTrip($ben);
    $cara = $berlin->employee;
    $hotel = expenseOn($berlin, attributes: ['paid_on' => '2026-12-05', 'category' => TravelExpenseCategory::Accommodation, 'description' => 'Hotel, two nights', 'amount' => 199000]);
    $flight = expenseOn($berlin, attributes: ['paid_on' => '2026-12-01', 'category' => TravelExpenseCategory::Transport, 'description' => 'Flight', 'amount' => 45050]);
    expenseOn($berlin, 'approved');
    expenseOn($berlin, 'declined');
    TravelRequest::factory()->on($berlin->assignment)->approved()->create(['first_date' => '2026-11-02', 'last_date' => '2026-11-03']);
    $dev = Employee::factory()->former()->reportingTo($ben)->create(['name' => 'Dev']);
    $lisbon = TravelRequest::factory()->on(assignmentOf($dev, 'Website'))->cancelledAfterApproval()->create(['destination' => 'Lisbon', 'first_date' => '2026-11-16', 'last_date' => '2026-11-17']);
    $meal = expenseOn($lisbon, attributes: ['paid_on' => '2026-11-16', 'category' => TravelExpenseCategory::Meals, 'description' => 'Dinner', 'amount' => 8000]);
    expenseOn(TravelRequest::factory()->on(assignmentOf(Employee::factory()->reportingTo($cara)->create()))->approved()->create());
    expenseOn(TravelRequest::factory()->on(assignmentOf($ben))->approved()->create());

    actingAs($ben)->get(route('approvals.index', ['tab' => 'travel-expenses']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'travel-expenses')
            ->where('waiting', ['workEntries' => 0, 'timeOff' => 0, 'travelRequests' => 0, 'travelExpenses' => 3])
            ->where('travelExpensesToApprove', [
                ['id' => $lisbon->id, 'employee' => ['id' => $dev->id, 'name' => 'Dev'], 'destination' => 'Lisbon', 'first_date' => '2026-11-16', 'last_date' => '2026-11-17', 'expenses' => [
                    ['id' => $meal->id, 'paid_on' => '2026-11-16', 'category' => 'meals', 'description' => 'Dinner', 'amount' => "RON\u{a0}80.00"],
                ]],
                ['id' => $berlin->id, 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05', 'expenses' => [
                    ['id' => $flight->id, 'paid_on' => '2026-12-01', 'category' => 'transport', 'description' => 'Flight', 'amount' => "RON\u{a0}450.50"],
                    ['id' => $hotel->id, 'paid_on' => '2026-12-05', 'category' => 'accommodation', 'description' => 'Hotel, two nights', 'amount' => "RON\u{a0}1,990.00"],
                ]],
            ])
            ->missing('travelRequestsToApprove')
            ->missing('entries'));
});

it('shows an empty Travel Expenses tab when nothing is waiting', function () {
    $ben = managerBen();
    carasBerlinTrip($ben);

    actingAs($ben)->get(route('approvals.index', ['tab' => 'travel-expenses']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'travel-expenses')
            ->where('travelExpensesToApprove', []));
});
