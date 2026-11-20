<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseController;
use App\Http\Controllers\Travel\TravelExpenseExportController;
use App\Http\Controllers\Travel\TravelExpenseReopeningController;
use App\Http\Controllers\Travel\TravelRequestListController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseController::class, TravelRequestListController::class, TravelExpenseReopeningController::class, TravelExpenseExportController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

function travelReceiptFile(string $contents = '%PDF-1.4 hotel'): string
{
    $receipt = tempnam(sys_get_temp_dir(), 'receipt').'.pdf';
    file_put_contents($receipt, $contents);

    return $receipt;
}

it('adds a Travel Expense with its receipt in a sheet over an Approved Travel Request opened from the Travel page, which then shows it as entered', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);

    actingAs($cara);

    $page = visit(route('travel-requests.index'))
        ->click('internal:role=link[name="Berlin"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertSee('Money paid for this travel appears here')
        ->assertSee('Declined, not counted')
        ->click('internal:role=link[name="Add Travel Expense"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}/expenses/create")
        ->assertSeeIn('internal:role=dialog', 'Berlin');

    pickDate($page, '#travel-expense-paid-on', '2026-12-05')
        ->fill('#travel-expense-amount', '1990')
        ->click('#travel-expense-category')
        ->click('[role="option"] >> text=Accommodation')
        ->fill('#travel-expense-description', 'Hotel, three nights')
        ->attach('#travel-expense-receipt', travelReceiptFile())
        ->assertSeeIn('[data-test="file-drop-file"]', '.pdf')
        ->click('internal:role=dialog >> internal:role=button[name="Add Travel Expense"s]')
        ->assertSee('Travel Expense added.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertNotPresent('internal:role=dialog')
        ->assertSee('Hotel, three nights')
        ->assertPresent('internal:role=link[name="Receipt for the Accommodation expense paid on Dec 5, 2026"s]')
        ->navigate(route('travel-requests.index'))
        ->assertSeeIn('td:nth-child(5)', "RON\u{a0}1,990.00")
        ->assertSeeIn('td:nth-child(6)', "RON\u{a0}0.00")
        ->assertNoJavaScriptErrors();

    $expense = TravelExpense::query()->sole();
    expect($expense->paid_on->toDateString())->toBe('2026-12-05');
    expect($expense->amount)->toBe(199000);
    expect($expense->category)->toBe(TravelExpenseCategory::Accommodation);
    expect($expense->state)->toBe(TravelExpenseState::Pending);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($expense->receipt_path);
});

it('keeps the Add Travel Expense sheet from being sent while no Category is chosen', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);

    actingAs($cara);

    $page = pickDate(visit(route('travel-requests.expenses.create', $berlin)), '#travel-expense-paid-on', '2026-12-05')
        ->fill('#travel-expense-amount', '1990')
        ->fill('#travel-expense-description', 'Hotel, three nights')
        ->attach('#travel-expense-receipt', travelReceiptFile())
        ->click('internal:role=dialog >> internal:role=button[name="Add Travel Expense"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}/expenses/create")
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector(\'select[name="category"]\').validity.valueMissing'))->toBeTrue();
    expect(TravelExpense::query()->exists())->toBeFalse();
});

it('edits a Declined Travel Expense from the Callout under it, showing why and that saving sends it back as Pending, with a new receipt', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo($mona = Employee::factory()->create(['name' => 'Mona']))->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $expense = TravelExpense::factory()->for($berlin)->declined()->create(['paid_on' => '2026-12-04', 'category' => TravelExpenseCategory::Meals, 'description' => 'Team dinner', 'declined_by' => $mona->id, 'decline_reason' => 'Only your own meal.']);
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($expense->receipt_path, '%PDF-1.4 old');

    actingAs($cara);

    visit(route('travel-requests.show', $berlin))
        ->assertSee('Declined by Mona:')
        ->assertSee('Only your own meal.')
        ->click('internal:role=link[name="Edit the Meals expense paid on Dec 4, 2026"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}/expenses/{$expense->id}/edit")
        ->assertSeeIn('internal:role=dialog', 'Edit Travel Expense')
        ->assertSeeIn('internal:role=dialog', 'Receipt.pdf')
        ->assertSeeIn('internal:role=dialog', 'Saving sends this Travel Expense back to Mona as Pending.')
        ->fill('#travel-expense-description', 'My own dinner')
        ->attach('#travel-expense-receipt', travelReceiptFile('%PDF-1.4 new'))
        ->click('internal:role=dialog >> internal:role=button[name="Save Travel Expense"s]')
        ->assertSee('Travel Expense changed.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertNotPresent('internal:role=dialog')
        ->assertDontSee('Only your own meal.')
        ->assertNoJavaScriptErrors();

    $changed = $expense->fresh();
    expect($changed?->description)->toBe('My own dinner');
    expect($changed?->state)->toBe(TravelExpenseState::Pending);
    expect($changed?->receipt_path)->not->toBe($expense->receipt_path);
});

it('deletes a Travel Expense from its row\'s menu after asking', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    TravelExpense::factory()->for($berlin)->create(['paid_on' => '2026-12-04', 'category' => TravelExpenseCategory::Transport, 'description' => 'Taxi']);

    actingAs($cara);

    visit(route('travel-requests.show', $berlin))
        ->click('internal:role=button[name="More for the Transport expense paid on Dec 4, 2026"s]')
        ->click('internal:role=menuitem[name="Delete"s]')
        ->assertSee('and its receipt are deleted for good.')
        ->click('internal:role=dialog >> internal:role=button[name="Delete Travel Expense"s]')
        ->assertSee('Travel Expense deleted.')
        ->assertDontSee('Taxi')
        ->assertNoJavaScriptErrors();

    expect(TravelExpense::query()->exists())->toBeFalse();
});

it('adds a Travel Expense from the bottom sheet on a phone, attaching its receipt, without scrolling sideways', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    TravelExpense::factory()->for($berlin)->declined()->create(['paid_on' => '2026-12-03', 'description' => 'Taxi', 'decline_reason' => 'Use the train.']);

    actingAs($cara);

    $page = visit(route('travel-requests.show', $berlin))
        ->resize(390, 844)
        ->assertSee('Use the train.')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=link[name="Add Travel Expense"s]')
        ->assertPathIs("/travel-requests/{$berlin->id}/expenses/create")
        ->assertSee('Take photo');

    pickDate($page, '#travel-expense-paid-on', '2026-12-05')
        ->fill('#travel-expense-amount', '1990')
        ->click('#travel-expense-category')
        ->click('[role="option"] >> text=Accommodation')
        ->fill('#travel-expense-description', 'Hotel, three nights')
        ->attach('#travel-expense-receipt', travelReceiptFile())
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=dialog >> internal:role=button[name="Add Travel Expense"s]')
        ->assertSee('Travel Expense added.')
        ->assertSee('Hotel, three nights')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect(TravelExpense::query()->where('description', 'Hotel, three nights')->exists())->toBeTrue();
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
        ->click('internal:role=button[name="Reopen 1 Travel Expense…"s]')
        ->assertSee('Reopen 1 Travel Expense?')
        ->click('internal:role=dialog >> internal:role=button[name="Reopen 1 Travel Expense"s]')
        ->assertSee('1 Travel Expense reopened.')
        ->assertPathIs("/travel-requests/{$berlin->id}")
        ->assertNoJavaScriptErrors();

    expect($toReopen->refresh()->state)->toBe(TravelExpenseState::Pending)
        ->and($stillApproved->refresh()->state)->toBe(TravelExpenseState::Approved);
});

it('offers the Approved Travel Expenses of the Employee shown on the Travel Requests page as a file', function () {
    $cara = Employee::factory()->create(['name' => 'Cara']);
    TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);

    actingAs(employeeWith([Permission::ViewTravel->value => Scope::Everyone]));

    visit(route('travel-request-list.index', ['employee' => $cara->id]))
        ->assertSee('Berlin')
        ->click('Export expenses')
        ->assertAttributeContains('internal:role=menuitem[name="CSV"s]', 'href', "/travel-expenses/export?month=2026-12&employee={$cara->id}&format=csv")
        ->assertAttributeContains('internal:role=menuitem[name="Excel"s]', 'href', "/travel-expenses/export?month=2026-12&employee={$cara->id}&format=xlsx")
        ->assertNoJavaScriptErrors();
});
