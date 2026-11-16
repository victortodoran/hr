<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseListController;
use App\Http\Controllers\Travel\TravelExpenseLockController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Requests\Travel\TravelExpenseListRequest;
use App\Models\Assignment;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\TravelExpensePolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseLockController::class, TravelExpenseListController::class, TravelExpenseListRequest::class, TravelExpensePolicy::class, TravelExpenseReceiptController::class, TravelExpenseBuilder::class, TravelExpense::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

/**
 * Finn, who locks Travel Expenses company-wide and nothing else.
 */
function travelLocker(): Employee
{
    return employeeWith([Permission::LockTravelExpenses->value => null], ['name' => 'Finn']);
}

/**
 * A Travel Expense paid on the given day on an Approved trip on the
 * Assignment, Approved unless given another factory state, its receipt
 * stored on the disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function lockableExpenseOn(Assignment $assignment, string $state = 'approved', string $paidOn = '2026-12-04', array $attributes = []): TravelExpense
{
    $trip = TravelRequest::factory()->on($assignment)->approved()->create(['first_date' => '2026-11-02', 'last_date' => '2026-12-05']);
    $path = TravelExpense::RECEIPT_FOLDER.'/'.fake()->uuid().'.pdf';
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($path, 'receipt');
    $factory = TravelExpense::factory()->for($trip);

    return ($state === 'pending' ? $factory : $factory->{$state}())->create(['paid_on' => $paidOn, 'receipt_path' => $path, ...$attributes]);
}

it('lists everyone\'s Travel Expenses, the locker\'s own included, offering to lock only the Approved ones', function () {
    $finn = travelLocker();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $carasApproved = lockableExpenseOn(assignmentOf($cara), paidOn: '2026-12-02');
    $ownApproved = lockableExpenseOn(assignmentOf($finn), paidOn: '2026-12-03');
    $carasPending = lockableExpenseOn(assignmentOf($cara), 'pending');
    $carasLocked = lockableExpenseOn(assignmentOf($cara), 'locked', '2026-12-05');

    $response = actingAs($finn)->get(route('travel-expense-list.index', ['employee' => 'all']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canReopen', false)
        ->where('canLock', true)
        ->where('expenses.data', fn (Collection $data): bool => $data->map(fn (array $row): array => [$row['id'], $row['can_reopen'], $row['can_lock']])->all() === [
            [$carasApproved->id, false, true],
            [$ownApproved->id, false, true],
            [$carasPending->id, false, false],
            [$carasLocked->id, false, false],
        ])
        ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 3], ['id' => $finn->id, 'name' => 'Finn', 'count' => 1]]));
});

it('filters by Employee, Project, month and state, totalling all but the Declined, and lists every match\'s ID for select-all', function () {
    $finn = travelLocker();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $relaunch = assignmentOf($cara);
    $billing = assignmentOf($cara, 'Billing');
    $match = lockableExpenseOn($relaunch, attributes: ['amount' => 10000]);
    $second = lockableExpenseOn($relaunch, paidOn: '2026-12-01', attributes: ['amount' => 5050]);
    lockableExpenseOn($relaunch, 'pending');
    lockableExpenseOn($billing);
    lockableExpenseOn($relaunch, paidOn: '2026-11-30');
    lockableExpenseOn(assignmentOf(Employee::factory()->create(), 'Relaunch'));

    $query = ['employee' => $cara->id, 'project' => $relaunch->project_id, 'month' => '2026-12', 'state' => 'approved'];

    actingAs($finn)->get(route('travel-expense-list.index', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', $cara->id)
            ->where('filters.project', $relaunch->project_id)
            ->where('expenses.data', fn (Collection $data): bool => $data->pluck('id')->all() === [$second->id, $match->id])
            ->where('total', "RON\u{a0}150.50")
            ->where('projects', [['id' => $billing->project_id, 'name' => 'Billing'], ['id' => $relaunch->project_id, 'name' => 'Relaunch']])
            ->missing('matchingIds')
            ->reloadOnly('matchingIds', fn (Assert $reload) => $reload->where('matchingIds', [$match->id, $second->id])));
});

it('leaves Declined expenses out of the total', function () {
    $cara = assignmentOf(Employee::factory()->create());
    lockableExpenseOn($cara, attributes: ['amount' => 10000]);
    lockableExpenseOn($cara, 'declined', attributes: ['amount' => 99900]);

    actingAs(travelLocker())->get(route('travel-expense-list.index'))
        ->assertInertia(fn (Assert $page) => $page->where('total', "RON\u{a0}100.00"));
});

it('locks the selected Approved expenses, recording who and when, and skips Pending and Declined ones', function () {
    $finn = travelLocker();
    $relaunch = assignmentOf(Employee::factory()->create());
    $approved = lockableExpenseOn($relaunch);
    $own = lockableExpenseOn(assignmentOf($finn));
    $pending = lockableExpenseOn($relaunch, 'pending');
    $declined = lockableExpenseOn($relaunch, 'declined');

    actingAs($finn)->from(route('travel-expense-list.index'))
        ->post(route('travel-expenses.locks.store'), ['ids' => [$approved->id, $own->id, $pending->id, $declined->id]])
        ->assertRedirect(route('travel-expense-list.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 Travel Expenses locked, 2 skipped.']);

    foreach ([$approved, $own] as $locked) {
        $locked->refresh();
        expect($locked->state)->toBe(TravelExpenseState::Locked);
        expect($locked->locked_by)->toBe($finn->id);
        expect($locked->locked_at)->toEqual(now());
    }
    expect($pending->refresh()->state)->toBe(TravelExpenseState::Pending);
    expect($declined->refresh()->state)->toBe(TravelExpenseState::Declined);
});

it('doesn\'t fail when the same lock is sent twice, keeping the first lock', function () {
    $finn = travelLocker();
    $expense = lockableExpenseOn(assignmentOf(Employee::factory()->create()));

    actingAs($finn)->post(route('travel-expenses.locks.store'), ['ids' => [$expense->id]]);
    travelTo('2026-12-10 09:05');
    actingAs($finn)->post(route('travel-expenses.locks.store'), ['ids' => [$expense->id]])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Travel Expenses locked, 1 skipped.']);

    expect($expense->refresh()->locked_at?->toDateTimeString())->toBe('2026-12-10 09:00:00');
});

it('never lets a Locked expense be changed, deleted or reopened', function () {
    $cara = Employee::factory()->reportingTo(managerBen())->create();
    $expense = lockableExpenseOn(assignmentOf($cara), 'locked');
    $reopener = employeeWith([Permission::ReopenTravelExpenses->value => Scope::Everyone]);

    actingAs($cara)->put(route('travel-expenses.update', $expense), [
        'paid_on' => '2026-12-04', 'amount' => '10', 'category' => 'meals', 'description' => 'Lunch',
        'receipt' => UploadedFile::fake()->create('lunch.pdf', 20, 'application/pdf'),
    ])->assertForbidden();
    actingAs($cara)->delete(route('travel-expenses.destroy', $expense))->assertForbidden();
    actingAs($reopener)->post(route('travel-expenses.reopenings.store'), ['ids' => [$expense->id]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'No Travel Expenses reopened, 1 skipped.']);

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Locked);
});

it('refuses locking and the Travel Expenses page to someone without the Permission, even a Manager', function () {
    $ben = managerBen();
    $expense = lockableExpenseOn(assignmentOf(Employee::factory()->reportingTo($ben)->create()));

    actingAs($ben)->post(route('travel-expenses.locks.store'), ['ids' => [$expense->id]])->assertForbidden();
    actingAs($ben)->get(route('travel-expense-list.index'))->assertForbidden();
    actingAs($ben)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTravelExpenseList', false));

    expect($expense->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('lets a locker reach the Travel Expenses page from the menu while managing nobody, and open anyone\'s receipt', function () {
    $finn = travelLocker();
    $expense = lockableExpenseOn(assignmentOf(Employee::factory()->create()));

    actingAs($finn)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTravelExpenseList', true));
    actingAs($finn)->get(route('travel-expenses.receipt.show', $expense))->assertOk();
    actingAs(Employee::factory()->create())->get(route('travel-expenses.receipt.show', $expense))->assertForbidden();
});

it('rejects an unknown state', function () {
    actingAs(travelLocker())->get(route('travel-expense-list.index', ['state' => 'paid']))
        ->assertSessionHasErrors('state');
});
