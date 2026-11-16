<?php

use App\Actions\AddEmployee;
use App\Actions\InstallCompany;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\EmployeePolicy;
use App\Policies\TravelExpensePolicy;
use App\Support\EmployeePage;
use App\Support\TravelHistory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeePage::class, TravelHistory::class, EmployeePolicy::class, TravelExpensePolicy::class, TravelExpenseReceiptController::class, Permission::class);

beforeEach(function () {
    travelTo('2026-12-10 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
    Storage::fake(TravelExpense::RECEIPT_DISK);
});

/**
 * The Employee's Approved trip to Berlin on Relaunch, with a 1,990 RON
 * Accommodation expense whose receipt is stored on the disk.
 */
function berlinTripWithHotel(Employee $employee): TravelExpense
{
    $trip = TravelRequest::factory()->on(assignmentOf($employee))->approved()
        ->create(['destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05', 'purpose' => 'Checkout workshop']);
    $path = TravelExpense::RECEIPT_FOLDER.'/'.fake()->uuid().'.pdf';
    Storage::disk(TravelExpense::RECEIPT_DISK)->put($path, 'receipt');

    return TravelExpense::factory()->for($trip)->create(['paid_on' => '2026-12-04', 'category' => 'accommodation', 'description' => 'Hotel', 'amount' => 199000, 'receipt_path' => $path]);
}

/**
 * Ana, with view travel for the given Scope only, and Cara, her Report.
 *
 * @return array{Employee, Employee}
 */
function anaWithViewTravelAndCara(Scope $scope): array
{
    $ana = employeeWith([Permission::ViewTravel->value => $scope], ['name' => 'Ana']);

    return [$ana, Employee::factory()->reportingTo($ana)->create(['name' => 'Cara'])];
}

it('shows Cara\'s Travel Requests, latest first, each with its Travel Expenses and total, only to be seen, to Ana with view travel for her Reports', function () {
    [$ana, $cara] = anaWithViewTravelAndCara(Scope::Reports);
    $hotel = berlinTripWithHotel($cara);
    $declined = TravelExpense::factory()->for($hotel->travelRequest)->declined()->create(['paid_on' => '2026-12-05', 'category' => 'meals', 'description' => 'Dinner', 'amount' => 5000, 'decline_reason' => 'Company card']);
    $munich = TravelRequest::factory()->on(assignmentOf($cara, 'Mobile App'))->declined()
        ->create(['destination' => 'Munich', 'first_date' => '2026-12-21', 'last_date' => '2026-12-21', 'purpose' => 'Demo', 'decline_reason' => 'Release week']);

    actingAs($ana)->get(route('employees.show', ['employee' => $cara, 'tab' => 'travel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'travel')
            ->where('tabs', ['overview', 'travel'])
            ->where('travelRequests.0', [
                'id' => $munich->id, 'project' => 'Mobile App', 'client' => null, 'destination' => 'Munich',
                'first_date' => '2026-12-21', 'last_date' => '2026-12-21', 'days' => 1, 'purpose' => 'Demo',
                'state' => 'declined', 'decided_by' => $munich->decider?->name, 'decline_reason' => 'Release week',
                'can_cancel' => false, 'expenses' => null,
            ])
            ->where('travelRequests.1.destination', 'Berlin')
            ->where('travelRequests.1.state', 'approved')
            ->where('travelRequests.1.expenses', [
                'items' => [
                    ['id' => $hotel->id, 'paid_on' => '2026-12-04', 'category' => 'accommodation', 'description' => 'Hotel', 'amount' => "RON\u{a0}1,990.00", 'amount_typed' => '1990.00', 'state' => 'pending', 'decline_reason' => null, 'can_change' => false],
                    ['id' => $declined->id, 'paid_on' => '2026-12-05', 'category' => 'meals', 'description' => 'Dinner', 'amount' => "RON\u{a0}50.00", 'amount_typed' => '50.00', 'state' => 'declined', 'decline_reason' => 'Company card', 'can_change' => false],
                ],
                'total' => "RON\u{a0}1,990.00",
                'can_create' => false,
            ]));
    actingAs($ana)->get(route('travel-expenses.receipt.show', $hotel))->assertOk()->assertStreamedContent('receipt');
});

it('refuses Ana with view travel for her Reports the page and receipts of someone who isn\'t her Report', function () {
    [$ana] = anaWithViewTravelAndCara(Scope::Reports);
    $outsider = Employee::factory()->create();
    $hotel = berlinTripWithHotel($outsider);

    actingAs($ana)->get(route('employees.show', ['employee' => $outsider, 'tab' => 'travel']))->assertForbidden();
    actingAs($ana)->get(route('travel-expenses.receipt.show', $hotel))->assertForbidden();
});

it('shows anyone\'s travel and receipts to Ana with view travel for everyone', function () {
    [$ana] = anaWithViewTravelAndCara(Scope::Everyone);
    $outsider = Employee::factory()->create();
    $hotel = berlinTripWithHotel($outsider);

    actingAs($ana)->get(route('employees.show', ['employee' => $outsider, 'tab' => 'travel']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'travel')->has('travelRequests', 1));
    actingAs($ana)->get(route('travel-expenses.receipt.show', $hotel))->assertOk();
});

it('offers no Travel tab and refuses the receipts to someone who may see Cara\'s record but not her travel', function () {
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $hotel = berlinTripWithHotel($cara = Employee::factory()->create());

    actingAs($viewer)->get(route('employees.show', ['employee' => $cara, 'tab' => 'travel']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'overview')->where('tabs', ['overview'])->missing('travelRequests'));
    actingAs($viewer)->get(route('travel-expenses.receipt.show', $hotel))->assertForbidden();
});

it('lets a new Employee, given the starter Employee Role, see their own travel on their record', function () {
    Notification::fake();
    CompanySettings::query()->delete();
    $ana = app(InstallCompany::class)->install('Acme Software', 'RON', 'Engineering', 'Ana', 'ana@acme.test');
    $cara = app(AddEmployee::class)->add($ana, [
        'name' => 'Cara', 'email' => 'cara@acme.test', 'department_id' => Department::query()->sole()->id,
        'manager_id' => $ana->id, 'start_date' => '2026-11-02',
    ]);
    $cara->markEmailAsVerified();
    berlinTripWithHotel($cara);

    actingAs($cara)->get(route('employees.show', ['employee' => $cara, 'tab' => 'travel']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'travel')->has('travelRequests', 1));
});
