<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseController;
use App\Http\Controllers\Travel\TravelExpenseListController;
use App\Http\Controllers\Travel\TravelExpenseLockController;
use App\Http\Controllers\Travel\TravelExpenseReopeningController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseController::class, TravelExpenseListController::class, TravelExpenseReopeningController::class, TravelExpenseLockController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

it('opens an Approved Travel Request from its Expenses button and adds an expense with its receipt there, landing back on its page, then sees it entered on the Travel page', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $receipt = tempnam(sys_get_temp_dir(), 'receipt').'.pdf';
    file_put_contents($receipt, '%PDF-1.4 hotel');

    actingAs($cara);

    visit(route('travel-requests.index'))
        ->click('internal:role=link[name="Expenses"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertSee('Money paid for this travel appears here')
        ->click('Add expense')
        ->assertPathIs("/travel-requests/{$berlin->id}/expenses/create")
        ->assertSee('Berlin')
        ->fill('#travel-expense-paid-on', '2026-12-05')
        ->fill('#travel-expense-amount', '1990')
        ->click('#travel-expense-category')
        ->click('[role="option"] >> text=Accommodation')
        ->fill('#travel-expense-description', 'Hotel, three nights')
        ->attach('#travel-expense-receipt', $receipt)
        ->click('button[type="submit"]')
        ->assertSee('Travel Expense added.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertSee('Hotel, three nights')
        ->assertSeeLink('Receipt')
        ->assertSeeLink('Edit')
        ->navigate(route('travel-requests.index'))
        ->assertSeeIn('td:nth-child(3)', "RON\u{a0}1,990.00")
        ->assertSeeIn('td:nth-child(4)', "RON\u{a0}0.00")
        ->assertNoJavaScriptErrors();

    $expense = TravelExpense::query()->sole();
    expect($expense->amount)->toBe(199000);
    expect($expense->category)->toBe(TravelExpenseCategory::Accommodation);
    expect($expense->state)->toBe(TravelExpenseState::Pending);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($expense->receipt_path);
});

it('filters the Travel Expenses page to Approved expenses, then reopens one and locks another', function () {
    $finn = employeeWith([Permission::ReopenTravelExpenses->value => Scope::Everyone, Permission::LockTravelExpenses->value => null], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $toReopen = TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-12-03', 'category' => TravelExpenseCategory::Transport, 'description' => 'Flight']);
    $toLock = TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-12-04', 'category' => TravelExpenseCategory::Accommodation, 'description' => 'Hotel']);
    TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-12-05', 'category' => TravelExpenseCategory::Meals, 'description' => 'Dinner']);

    actingAs($finn);

    visit(route('travel-expense-list.index'))
        ->assertSee('Dinner')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Approved"s]')
        ->assertQueryStringHas('state', 'approved')
        ->assertDontSee('Dinner')
        ->click('[aria-label="Select Cara\'s Transport expense paid on Dec 3, 2026"]')
        ->click('Reopen')
        ->assertSee('1 Travel Expense reopened.')
        ->assertDontSee('Flight')
        ->click('[aria-label="Select Cara\'s Accommodation expense paid on Dec 4, 2026"]')
        ->click('internal:role=button[name="Lock"s]')
        ->assertSee('Lock 1 Travel Expense?')
        ->click('Lock for good')
        ->assertSee('1 Travel Expense locked.')
        ->assertNoJavaScriptErrors();

    expect($toReopen->refresh()->state)->toBe(TravelExpenseState::Pending)
        ->and($toLock->refresh()->state)->toBe(TravelExpenseState::Locked);
});
