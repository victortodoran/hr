<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseListController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Controllers\Travel\TravelExpenseReopeningController;
use App\Models\Builders\EmployeeBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\TravelExpensePolicy;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseReopeningController::class, TravelExpenseListController::class, TravelExpensePolicy::class, TravelExpenseReceiptController::class, EmployeeBuilder::class);

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

it('lists the Travel Expenses of everyone a reopen Scope covers, offering to reopen the Approved ones but never one\'s own', function () {
    $rae = travelReopener([Permission::ReopenTravelExpenses->value => Scope::Everyone]);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $cara = Employee::factory()->reportingTo($ben)->create(['name' => 'Cara']);
    $approved = reopenableExpenseOf($cara, attributes: ['amount' => 199000, 'category' => 'accommodation', 'description' => 'Hotel', 'approved_by' => $ben->id, 'approved_at' => '2026-12-09 17:00']);
    $pending = reopenableExpenseOf($cara, 'pending');
    $own = reopenableExpenseOf($rae);

    $response = actingAs($rae)->get(route('travel-expense-list.index', ['employee' => 'all']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('travel-expenses/List')
        ->where('filters', ['period' => ['mode' => 'month', 'from' => '2026-12-01', 'to' => '2026-12-31', 'query' => ['month' => '2026-12']], 'employee' => 'all', 'project' => null, 'state' => null])
        ->where('canReopen', true)
        ->where('canLock', false)
        ->where('expenses.data.0', ['id' => $approved->id, 'paid_on' => '2026-12-04', 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'destination' => 'Berlin', 'category' => 'accommodation', 'description' => 'Hotel', 'amount' => "RON\u{a0}1,990.00", 'state' => 'approved', 'approved' => ['by' => 'Ben', 'on' => '2026-12-09'], 'locked' => null, 'decline_reason' => null, 'can_reopen' => true, 'can_lock' => false])
        ->where('expenses.data.1.id', $pending->id)
        ->where('expenses.data.1.can_reopen', false)
        ->where('expenses.data.2.id', $own->id)
        ->where('expenses.data.2.can_reopen', false)
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 2], ['id' => $rae->id, 'name' => 'Rae', 'count' => 1]]));
});

it('lets Rae reopen her Report\'s Approved expense, withdrawing its approval, and skips a non-Report\'s and her own', function () {
    $rae = travelReopener();
    $report = reopenableExpenseOf(Employee::factory()->reportingTo($rae)->create());
    $outsider = reopenableExpenseOf(Employee::factory()->create());
    $own = reopenableExpenseOf($rae);

    actingAs($rae)->from(route('travel-expense-list.index'))
        ->post(route('travel-expenses.reopenings.store'), ['ids' => [$report->id, $outsider->id, $own->id]])
        ->assertRedirect(route('travel-expense-list.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense reopened, 2 skipped.']);

    $report->refresh();
    expect($report->state)->toBe(TravelExpenseState::Pending);
    expect($report->approved_by)->toBeNull();
    expect($report->approved_at)->toBeNull();
    expect($outsider->refresh()->state)->toBe(TravelExpenseState::Approved);
    expect($own->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('leaves a reopened expense for its Employee to change and their Manager to approve again', function () {
    $rae = travelReopener();
    $cara = Employee::factory()->reportingTo($rae)->create();
    $expense = reopenableExpenseOf($cara);

    actingAs($rae)->post(route('travel-expenses.reopenings.store'), ['ids' => [$expense->id]]);
    $expense->refresh();

    expect($cara->can('update', $expense))->toBeTrue();
    expect($cara->can('delete', $expense))->toBeTrue();
    actingAs($rae)->post(route('approvals.travel-expenses.store'), ['ids' => [$expense->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense approved.']);
    expect($expense->refresh()->state)->toBe(TravelExpenseState::Approved);
    expect($expense->approved_by)->toBe($rae->id);
});

it('skips expenses that aren\'t Approved, a Locked one included', function (string $state) {
    $rae = travelReopener();
    $expense = reopenableExpenseOf(Employee::factory()->reportingTo($rae)->create(), $state);

    actingAs($rae)->post(route('travel-expenses.reopenings.store'), ['ids' => [$expense->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Travel Expenses reopened, 1 skipped.']);

    expect($expense->refresh()->state->value)->toBe($state);
})->with(['pending', 'declined', 'locked']);

it('refuses reopening to a Manager without the Permission, even for their Report', function () {
    $ben = managerBen();
    $expense = reopenableExpenseOf(Employee::factory()->reportingTo($ben)->create());

    actingAs($ben)->post(route('travel-expenses.reopenings.store'), ['ids' => [$expense->id]])->assertForbidden();
    actingAs($ben)->get(route('travel-expense-list.index'))->assertForbidden();

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('lets a reopen holder open the receipts of their Reports\' expenses, but not of anyone else', function () {
    $rae = travelReopener();
    $report = reopenableExpenseOf(Employee::factory()->reportingTo($rae)->create());
    $outsider = reopenableExpenseOf(Employee::factory()->create());

    actingAs($rae)->get(route('travel-expenses.receipt.show', $report))->assertOk();
    actingAs($rae)->get(route('travel-expenses.receipt.show', $outsider))->assertForbidden();
});

it('shows the Travel Expenses page in the menu to reopen holders, but not to someone who reopens only Work Entries', function () {
    actingAs(travelReopener())->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTravelExpenseList', true));
    actingAs(employeeWith([Permission::ReopenWorkEntries->value => Scope::Everyone]))->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTravelExpenseList', false));
});

it('shows when an expense of an Employee with no Manager was approved and locked, and leaves it reopened for them to correct, which approves it again', function () {
    $rae = travelReopener([Permission::ReopenTravelExpenses->value => Scope::Everyone]);
    $ada = Employee::query()->findOrFail($rae->manager_id);
    $finn = Employee::factory()->create(['name' => 'Finn']);
    $locked = reopenableExpenseOf($ada, 'locked', ['approved_by' => null, 'approved_at' => '2026-12-04 10:00', 'locked_by' => $finn->id, 'locked_at' => '2026-12-09 10:00']);
    $approved = reopenableExpenseOf($ada, attributes: ['approved_by' => null, 'approved_at' => '2026-12-04 10:00']);

    actingAs($rae)->get(route('travel-expense-list.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('expenses.data.0.approved', ['by' => null, 'on' => '2026-12-04'])
            ->where('expenses.data.0.locked', ['by' => 'Finn', 'on' => '2026-12-09'])
            ->where('expenses.data.1.id', $approved->id)
            ->where('expenses.data.1.can_reopen', true));
    actingAs($rae)->post(route('travel-expenses.reopenings.store'), ['ids' => [$locked->id, $approved->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '1 Travel Expense reopened, 1 skipped.']);
    expect($approved->refresh()->state)->toBe(TravelExpenseState::Pending);

    actingAs($ada)->put(route('travel-expenses.update', $approved), [
        'paid_on' => '2026-12-04', 'amount' => '25', 'category' => 'meals', 'description' => 'Lunch, corrected',
    ])->assertRedirect(route('travel-requests.show', $approved->travel_request_id));

    expect($approved->refresh()->state)->toBe(TravelExpenseState::Approved);
});
