<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseController;
use App\Http\Controllers\Travel\TravelExpenseReopeningController;
use App\Http\Controllers\Travel\TravelRequestListController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseController::class, TravelRequestListController::class, TravelExpenseReopeningController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

it('adds an expense with its receipt to an Approved Travel Request opened from its Expenses link, which the Travel page then shows as entered', function () {
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

it('keeps the expense form from being sent while no category is chosen', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $receipt = tempnam(sys_get_temp_dir(), 'receipt').'.pdf';
    file_put_contents($receipt, '%PDF-1.4 hotel');

    actingAs($cara);

    $page = visit(route('travel-requests.expenses.create', $berlin))
        ->fill('#travel-expense-paid-on', '2026-12-05')
        ->fill('#travel-expense-amount', '1990')
        ->fill('#travel-expense-description', 'Hotel, three nights')
        ->attach('#travel-expense-receipt', $receipt)
        ->click('button[type="submit"]')
        ->assertPathIs("/travel-requests/{$berlin->id}/expenses/create")
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector(\'select[name="category"]\').validity.valueMissing'))->toBeTrue();
    expect(TravelExpense::query()->exists())->toBeFalse();
});

it('shows the current receipt when changing an expense, and saves a new file chosen to replace it', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $expense = TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-12-04']);
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($expense->receipt_path, '%PDF-1.4 old');
    $receipt = tempnam(sys_get_temp_dir(), 'receipt').'.pdf';
    file_put_contents($receipt, '%PDF-1.4 new');

    actingAs($cara);

    visit(route('travel-requests.expenses.edit', ['travelRequest' => $berlin, 'travelExpense' => $expense]))
        ->assertSee('Current receipt')
        ->assertSeeLink('Open the receipt')
        ->attach('#travel-expense-receipt', $receipt)
        ->click('Save')
        ->assertSee('Travel Expense changed.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertNoJavaScriptErrors();

    expect($expense->fresh()?->receipt_path)->not->toBe($expense->receipt_path);
});

it('finds a Travel Request on the Travel Requests page by its state, then reopens one of its expenses on its page and leaves the other Approved', function () {
    $finn = employeeWith([Permission::ReopenTravelExpenses->value => Scope::Everyone], ['name' => 'Finn']);
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    TravelRequest::factory()->on(assignmentOf($cara, 'Atlas'))->create(['destination' => 'Paris', 'first_date' => '2026-12-20', 'last_date' => '2026-12-21']);
    $toReopen = TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-12-03', 'category' => TravelExpenseCategory::Transport, 'description' => 'Flight']);
    $stillApproved = TravelExpense::factory()->for($berlin)->approved()->create(['paid_on' => '2026-12-04', 'category' => TravelExpenseCategory::Accommodation, 'description' => 'Hotel']);
    TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-12-05', 'category' => TravelExpenseCategory::Meals, 'description' => 'Dinner']);

    actingAs($finn);

    visit(route('travel-request-list.index'))
        ->assertSee('Paris')
        ->click('[aria-label="State"]')
        ->click('internal:role=option[name="Approved"s]')
        ->assertQueryStringHas('state', 'approved')
        ->assertDontSee('Paris')
        ->assertSee('1 Pending')
        ->click('internal:role=link[name="Berlin"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->click('[aria-label="Select the Transport expense paid on Dec 3, 2026"]')
        ->click('Reopen')
        ->assertSee('Reopen 1 Travel Expense?')
        ->click('internal:role=dialog >> internal:role=button[name="Reopen"s]')
        ->assertSee('1 Travel Expense reopened.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertNoJavaScriptErrors();

    expect($toReopen->refresh()->state)->toBe(TravelExpenseState::Pending)
        ->and($stillApproved->refresh()->state)->toBe(TravelExpenseState::Approved);
});
