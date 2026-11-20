<?php

use App\Enums\TravelExpenseCategory;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Travel\TravelExpenseController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Http\Requests\Travel\TravelExpenseRequest;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\TravelExpensePolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelExpenseController::class, TravelExpenseReceiptController::class, TravelExpenseRequest::class, TravelExpensePolicy::class, TravelExpense::class, TravelRequest::class, TravelRequestController::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

/**
 * Cara, who reports to Mona.
 */
function expenseRecorder(): Employee
{
    return Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->startedLongAgo()->create(['name' => 'Mona']))->create(['name' => 'Cara']);
}

/**
 * Cara's trip to Berlin from 3 to 5 Dec 2026, Approved unless given another
 * factory state, or pending.
 */
function berlinTrip(Employee $cara, string $state = 'approved'): TravelRequest
{
    $factory = TravelRequest::factory()->on(assignmentOf($cara));

    return ($state === 'pending' ? $factory : $factory->{$state}())->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05']);
}

/**
 * A Travel Expense as its form sends it: 1,990 RON of Accommodation paid
 * on 5 Dec 2026, with a PDF receipt, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function expenseForm(array $changes = []): array
{
    return [
        'paid_on' => '2026-12-05',
        'category' => 'accommodation',
        'amount' => '1990.00',
        'description' => 'Hotel, three nights',
        'receipt' => UploadedFile::fake()->create('hotel.pdf', 200, 'application/pdf'),
        ...$changes,
    ];
}

/**
 * A Travel Expense on the Travel Request, its receipt stored on the disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function recordedExpense(TravelRequest $travelRequest, string $state = 'pending', array $attributes = []): TravelExpense
{
    $path = TravelExpense::RECEIPT_FOLDER.'/'.fake()->uuid().'.pdf';
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($path, 'receipt');
    $factory = TravelExpense::factory()->for($travelRequest);

    return ($state === 'pending' ? $factory : $factory->{$state}())->create(['receipt_path' => $path, ...$attributes]);
}

it('lets Cara record a 1,990 RON Accommodation expense with a PDF receipt on her Approved Berlin trip, which is Pending', function () {
    $cara = expenseRecorder();
    $berlin = berlinTrip($cara);

    actingAs($cara)->post(route('travel-requests.expenses.store', $berlin), expenseForm())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('travel-requests.show', $berlin))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Expense added.']);

    $expense = TravelExpense::query()->sole();
    expect($expense->travel_request_id)->toBe($berlin->id);
    expect($expense->amount)->toBe(199000);
    expect($expense->category)->toBe(TravelExpenseCategory::Accommodation);
    expect($expense->paid_on->toDateString())->toBe('2026-12-05');
    expect($expense->description)->toBe('Hotel, three nights');
    expect($expense->state)->toBe(TravelExpenseState::Pending);
    expect($expense->approved_at)->toBeNull();
    expect($expense->receipt_path)->toStartWith(TravelExpense::RECEIPT_FOLDER.'/');
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($expense->receipt_path);
});

it('lists the expenses of a Travel Request that takes them on its page, in the order paid, with who declined each and whether its receipt is a picture, and the totals entered, Approved, Pending and Declined', function () {
    $cara = expenseRecorder();
    $berlin = berlinTrip($cara);
    $hotel = recordedExpense($berlin, 'declined', ['paid_on' => '2026-12-05', 'amount' => 199000, 'category' => TravelExpenseCategory::Accommodation, 'description' => 'Hotel', 'decline_reason' => 'Book through travel desk', 'declined_by' => Employee::query()->where('name', 'Mona')->sole()->id]);
    $train = recordedExpense($berlin, 'approved', ['paid_on' => '2026-12-03', 'amount' => 25050, 'category' => TravelExpenseCategory::Transport, 'description' => 'Train', 'receipt_path' => TravelExpense::RECEIPT_FOLDER.'/ticket.JPG']);
    $taxi = recordedExpense($berlin, 'pending', ['paid_on' => '2026-12-04', 'amount' => 3000, 'category' => TravelExpenseCategory::Transport, 'description' => 'Taxi']);
    $pending = berlinTrip($cara, 'cancelled');

    actingAs($cara)->get(route('travel-requests.show', $berlin))
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Show')
            ->missing('expenseSheet')
            ->where('travelRequest.expenses', [
                'items' => [
                    ['id' => $train->id, 'paid_on' => '2026-12-03', 'category' => 'transport', 'description' => 'Train', 'amount' => "RON\u{a0}250.50", 'state' => 'approved', 'decline_reason' => null, 'declined_by' => null, 'receipt_is_image' => true, 'can_change' => false, 'can_decide' => false, 'can_reopen' => false],
                    ['id' => $taxi->id, 'paid_on' => '2026-12-04', 'category' => 'transport', 'description' => 'Taxi', 'amount' => "RON\u{a0}30.00", 'state' => 'pending', 'decline_reason' => null, 'declined_by' => null, 'receipt_is_image' => false, 'can_change' => true, 'can_decide' => false, 'can_reopen' => false],
                    ['id' => $hotel->id, 'paid_on' => '2026-12-05', 'category' => 'accommodation', 'description' => 'Hotel', 'amount' => "RON\u{a0}1,990.00", 'state' => 'declined', 'decline_reason' => 'Book through travel desk', 'declined_by' => 'Mona', 'receipt_is_image' => false, 'can_change' => true, 'can_decide' => false, 'can_reopen' => false],
                ],
                'total' => "RON\u{a0}280.50",
                'approved_total' => "RON\u{a0}250.50",
                'pending_total' => "RON\u{a0}30.00",
                'declined_total' => "RON\u{a0}1,990.00",
                'can_create' => true,
            ]));
    actingAs($cara)->get(route('travel-requests.show', $pending))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequest.expenses', null));
});

it('opens the Add Travel Expense sheet over the Travel Request\'s page, with the company currency and today', function () {
    $cara = expenseRecorder();
    $berlin = berlinTrip($cara);

    actingAs($cara)->get(route('travel-requests.expenses.create', $berlin))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Show')
            ->where('travelRequest.id', $berlin->id)
            ->where('travelRequest.expenses.can_create', true)
            ->where('expenseSheet', 'add')
            ->where('currency', ['code' => 'RON', 'decimals' => 2])
            ->where('today', '2026-12-10')
            ->missing('travelExpense'));
});

it('refuses the form for adding an expense on a Travel Request that was never Approved, or someone else\'s', function () {
    $cara = expenseRecorder();
    $mona = Employee::query()->where('name', 'Mona')->sole();

    actingAs($cara)->get(route('travel-requests.expenses.create', berlinTrip($cara, 'pending')))->assertForbidden();
    actingAs($mona)->get(route('travel-requests.expenses.create', berlinTrip($cara)))->assertForbidden();
});

it('opens the Edit Travel Expense sheet over the Travel Request\'s page, filled in as it would be typed, with why it was declined and the Manager it goes back to', function () {
    $cara = expenseRecorder();
    $berlin = berlinTrip($cara);
    $expense = recordedExpense($berlin, 'declined', ['paid_on' => '2026-12-04', 'amount' => 25050, 'category' => TravelExpenseCategory::Transport, 'description' => 'Train', 'decline_reason' => 'Book through travel desk', 'declined_by' => $cara->manager_id, 'receipt_path' => TravelExpense::RECEIPT_FOLDER.'/ticket.PNG']);

    actingAs($cara)->get(route('travel-requests.expenses.edit', [$berlin, $expense]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Show')
            ->where('travelRequest.id', $berlin->id)
            ->where('expenseSheet', 'edit')
            ->where('currency', ['code' => 'RON', 'decimals' => 2])
            ->where('today', '2026-12-10')
            ->where('travelExpense', ['id' => $expense->id, 'paid_on' => '2026-12-04', 'category' => 'transport', 'amount' => '250.50', 'description' => 'Train', 'state' => 'declined', 'decline_reason' => 'Book through travel desk', 'declined_by' => 'Mona', 'receipt_name' => 'Receipt.png'])
            ->where('manager', 'Mona'));
});

it('refuses the form for changing an expense once Approved, someone else\'s, or under another Travel Request', function () {
    $cara = expenseRecorder();
    $berlin = berlinTrip($cara);
    $mona = Employee::query()->where('name', 'Mona')->sole();
    $other = TravelRequest::factory()->on(assignmentOf($cara, 'Mobile App'))->approved()->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-07']);

    actingAs($cara)->get(route('travel-requests.expenses.edit', [$berlin, recordedExpense($berlin, 'approved')]))->assertForbidden();
    actingAs($mona)->get(route('travel-requests.expenses.edit', [$berlin, $pending = recordedExpense($berlin)]))->assertForbidden();
    actingAs($cara)->get(route('travel-requests.expenses.edit', [$other, $pending]))->assertNotFound();
});

it('refuses expenses on a Travel Request that was never Approved', function (string $state) {
    $cara = expenseRecorder();
    $trip = berlinTrip($cara, $state);

    actingAs($cara)->post(route('travel-requests.expenses.store', $trip), expenseForm())
        ->assertForbidden();

    expect(TravelExpense::query()->exists())->toBeFalse();
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertDirectoryEmpty(TravelExpense::RECEIPT_FOLDER);
})->with([
    'Pending' => ['state' => 'pending'],
    'Declined' => ['state' => 'declined'],
    'Cancelled while Pending' => ['state' => 'cancelled'],
]);

it('takes expenses on a Travel Request Cancelled after it was Approved, so money already spent is paid back', function () {
    $cara = expenseRecorder();
    $trip = berlinTrip($cara, 'cancelledAfterApproval');

    actingAs($cara)->post(route('travel-requests.expenses.store', $trip), expenseForm(['category' => 'transport', 'description' => 'Non-refundable flight']))
        ->assertSessionHasNoErrors();

    expect(TravelExpense::query()->sole()->travel_request_id)->toBe($trip->id);
});

it('refuses an expense that is not valid', function (array $changes, string $field) {
    $cara = expenseRecorder();

    actingAs($cara)->post(route('travel-requests.expenses.store', berlinTrip($cara)), expenseForm($changes))
        ->assertSessionHasErrors($field);

    expect(TravelExpense::query()->exists())->toBeFalse();
})->with([
    'a missing receipt' => [['receipt' => null], 'receipt'],
    'a receipt of the wrong type' => [fn () => ['receipt' => UploadedFile::fake()->create('notes.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')], 'receipt'],
    'a receipt over 10 MB' => [fn () => ['receipt' => UploadedFile::fake()->create('scan.pdf', 10 * 1024 + 1, 'application/pdf')], 'receipt'],
    'a zero amount' => [['amount' => '0'], 'amount'],
    'a negative amount' => [['amount' => '-5'], 'amount'],
    'more decimals than the currency has' => [['amount' => '10.555'], 'amount'],
    'a date still to come' => [['paid_on' => '2026-12-11'], 'paid_on'],
    'an unknown category' => [['category' => 'souvenirs'], 'category'],
    'no description' => [['description' => ''], 'description'],
]);

it('accepts a receipt of exactly 10 MB as a JPG or a PNG, paid before the trip and today', function (string $name, string $mime, int $kilobytes, string $paidOn) {
    $cara = expenseRecorder();

    actingAs($cara)->post(route('travel-requests.expenses.store', berlinTrip($cara)), expenseForm(['paid_on' => $paidOn, 'receipt' => UploadedFile::fake()->create($name, $kilobytes, $mime)]))
        ->assertSessionHasNoErrors();
})->with([
    'a JPG paid weeks before' => ['taxi.jpg', 'image/jpeg', 10 * 1024, '2026-11-01'],
    'a PNG paid today' => ['taxi.png', 'image/png', 300, '2026-12-10'],
]);

it('approves the expense of an Employee with no Manager at once, with nobody approving', function () {
    $founder = Employee::factory()->startedLongAgo()->create();
    $trip = TravelRequest::factory()->on(assignmentOf($founder))->create(['state' => 'approved', 'decided_at' => now()]);

    actingAs($founder)->post(route('travel-requests.expenses.store', $trip), expenseForm())
        ->assertSessionHasNoErrors();

    $expense = TravelExpense::query()->sole();
    expect($expense->state)->toBe(TravelExpenseState::Approved);
    expect($expense->approved_by)->toBeNull();
    expect($expense->approved_at?->toDateTimeString())->toBe('2026-12-10 09:00:00');
});

it('sends a Declined expense back to Pending without its reason when Cara changes it, replacing its receipt', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara), 'declined');
    $oldReceipt = $expense->receipt_path;

    actingAs($cara)->put(route('travel-expenses.update', $expense), expenseForm(['amount' => '1850', 'receipt' => UploadedFile::fake()->create('hotel.png', 100, 'image/png')]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('travel-requests.show', $expense->travel_request_id))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Expense changed.']);

    $expense->refresh();
    expect($expense->state)->toBe(TravelExpenseState::Pending);
    expect($expense->amount)->toBe(185000);
    expect($expense->declined_by)->toBeNull();
    expect($expense->declined_at)->toBeNull();
    expect($expense->decline_reason)->toBeNull();
    expect($expense->receipt_path)->not->toBe($oldReceipt);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertMissing($oldReceipt);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($expense->receipt_path);
});

it('approves a changed expense at once for an Employee who no longer has a Manager', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara), 'declined');
    $cara->update(['manager_id' => null]);

    actingAs($cara)->put(route('travel-expenses.update', $expense), expenseForm(['receipt' => null]))
        ->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->state)->toBe(TravelExpenseState::Approved);
    expect($expense->approved_at?->toDateTimeString())->toBe('2026-12-10 09:00:00');
    expect($expense->decline_reason)->toBeNull();
});

it('keeps the receipt when a change sends no new one', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara));
    $receipt = $expense->receipt_path;

    actingAs($cara)->put(route('travel-expenses.update', $expense), expenseForm(['description' => 'Hotel, two nights', 'receipt' => null]))
        ->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->description)->toBe('Hotel, two nights');
    expect($expense->receipt_path)->toBe($receipt);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($receipt);
});

it('lets Cara delete her Pending expense, with its receipt', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara));

    actingAs($cara)->delete(route('travel-expenses.destroy', $expense))
        ->assertRedirect(route('travel-requests.show', $expense->travel_request_id))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Travel Expense deleted.']);

    expect(TravelExpense::query()->exists())->toBeFalse();
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertMissing($expense->receipt_path);
});

it('refuses changing or deleting an expense once it is Approved', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara), 'approved');

    actingAs($cara)->put(route('travel-expenses.update', $expense), expenseForm(['amount' => '1']))->assertForbidden();
    actingAs($cara)->delete(route('travel-expenses.destroy', $expense))->assertForbidden();

    expect($expense->fresh()?->amount)->toBe($expense->amount);
    Storage::disk(TravelExpense::RECEIPT_DISK)->assertExists($expense->receipt_path);
});

it('refuses recording, changing or deleting an expense on someone else\'s Travel Request', function () {
    $cara = expenseRecorder();
    $berlin = berlinTrip($cara);
    $expense = recordedExpense($berlin);
    $mona = Employee::query()->where('name', 'Mona')->sole();

    actingAs($mona)->post(route('travel-requests.expenses.store', $berlin), expenseForm())->assertForbidden();
    actingAs($mona)->put(route('travel-expenses.update', $expense), expenseForm())->assertForbidden();
    actingAs($mona)->delete(route('travel-expenses.destroy', $expense))->assertForbidden();

    expect(TravelExpense::query()->sole()->is($expense))->toBeTrue();
});

it('serves Cara her own receipt and refuses it to another Employee', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara));

    actingAs($cara)->get(route('travel-expenses.receipt.show', $expense))
        ->assertOk()
        ->assertStreamedContent('receipt');

    actingAs(Employee::factory()->startedLongAgo()->create())->get(route('travel-expenses.receipt.show', $expense))
        ->assertForbidden();
});

it('answers Not Found when a receipt\'s file is gone', function () {
    $cara = expenseRecorder();
    $expense = recordedExpense(berlinTrip($cara));
    Storage::disk(TravelExpense::RECEIPT_DISK)->delete($expense->receipt_path);

    actingAs($cara)->get(route('travel-expenses.receipt.show', $expense))
        ->assertNotFound();
});
