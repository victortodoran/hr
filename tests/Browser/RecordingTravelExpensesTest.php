<?php

use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseController;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

it('adds an expense with its receipt to an Approved Travel Request from the dialog', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $berlin = TravelRequest::factory()->on(assignmentOf($cara))->approved()->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
    $receipt = tempnam(sys_get_temp_dir(), 'receipt').'.pdf';
    file_put_contents($receipt, '%PDF-1.4 hotel');

    actingAs($cara);

    visit(route('travel-requests.index'))
        ->assertSee('Money you paid yourself for this travel appears here')
        ->click('Add expense')
        ->assertVisible('internal:role=dialog')
        ->fill("#travel-expense-new-{$berlin->id}-paid-on", '2026-12-05')
        ->fill("#travel-expense-new-{$berlin->id}-amount", '1990')
        ->click("#travel-expense-new-{$berlin->id}-category")
        ->click('[role="option"] >> text=Accommodation')
        ->fill("#travel-expense-new-{$berlin->id}-description", 'Hotel, three nights')
        ->attach("#travel-expense-new-{$berlin->id}-receipt", $receipt)
        ->click('internal:role=dialog >> button[type="submit"]')
        ->assertSee('Travel Expense added.')
        ->assertMissing('internal:role=dialog')
        ->assertSee('Hotel, three nights')
        ->assertSee('Receipt')
        ->assertNoJavaScriptErrors();

    $expense = TravelExpense::query()->sole();
    expect($expense->amount)->toBe(199000);
    expect($expense->category)->toBe(TravelExpenseCategory::Accommodation);
    expect($expense->state)->toBe(TravelExpenseState::Pending);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($expense->receipt_path);
});
