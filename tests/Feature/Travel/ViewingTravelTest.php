<?php

use App\Actions\AddEmployee;
use App\Actions\InstallCompany;
use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\EmployeePolicy;
use App\Policies\TravelExpensePolicy;
use App\Policies\TravelRequestPolicy;
use App\Support\EmployeePage;
use App\Support\TravelHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeePage::class, TravelHistory::class, EmployeePolicy::class, TravelExpensePolicy::class, TravelExpenseReceiptController::class, TravelRequestController::class, TravelRequestPolicy::class, Permission::class);

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

/**
 * Cara's travel on 10 Dec 2026, from the end of her Berlin trip, Approved
 * with its 1,990 RON hotel and a Declined 50 RON dinner: Lisbon, Approved
 * and underway until today; Rome, Approved from the 12th; Vienna, Pending
 * on the 20th, for Globex; Munich, Declined for the 15th; and Paris,
 * Cancelled after it was Approved, for the 18th.
 *
 * @return array<string, TravelRequest>
 */
function carasTravel(Employee $cara): array
{
    $berlin = berlinTripWithHotel($cara)->travelRequest;
    TravelExpense::factory()->for($berlin)->declined()->create(['paid_on' => '2026-12-05', 'category' => 'meals', 'description' => 'Dinner', 'amount' => 5000, 'decline_reason' => 'Company card']);
    $relaunch = $berlin->assignment;
    $globex = Assignment::factory()->for($cara)->for(Project::factory()->forClient(Client::factory()->create(['name' => 'Globex']))->create(['start_date' => '2026-01-05']))
        ->create(['start_date' => '2026-11-02', 'end_date' => null]);

    return [
        'berlin' => $berlin,
        'lisbon' => TravelRequest::factory()->on($relaunch)->approved()->create(['destination' => 'Lisbon', 'first_date' => '2026-12-09', 'last_date' => '2026-12-10']),
        'rome' => TravelRequest::factory()->on($relaunch)->approved()->create(['destination' => 'Rome', 'first_date' => '2026-12-12', 'last_date' => '2026-12-13']),
        'vienna' => TravelRequest::factory()->on($globex)->create(['destination' => 'Vienna', 'first_date' => '2026-12-20', 'last_date' => '2026-12-21']),
        'munich' => TravelRequest::factory()->on($relaunch)->declined()->create(['destination' => 'Munich', 'first_date' => '2026-12-15', 'last_date' => '2026-12-15']),
        'paris' => TravelRequest::factory()->on($relaunch)->cancelledAfterApproval()->create(['destination' => 'Paris', 'first_date' => '2026-12-18', 'last_date' => '2026-12-18']),
    ];
}

it('splits the Employee\'s own travel into Upcoming, soonest first, and Past, newest first, each row with its Client, expense total and Declined expenses', function () {
    $cara = Employee::factory()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $travel = carasTravel($cara);
    TravelRequest::factory()->create();

    actingAs($cara)->get(route('travel-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Index')
            ->where('travelRequests.upcoming', [
                ['id' => $travel['lisbon']->id, 'destination' => 'Lisbon', 'first_date' => '2026-12-09', 'last_date' => '2026-12-10', 'client' => null, 'state' => 'approved', 'expense_total' => "RON\u{a0}0.00", 'counted_expenses' => 0, 'declined_expenses' => 0],
                ['id' => $travel['rome']->id, 'destination' => 'Rome', 'first_date' => '2026-12-12', 'last_date' => '2026-12-13', 'client' => null, 'state' => 'approved', 'expense_total' => "RON\u{a0}0.00", 'counted_expenses' => 0, 'declined_expenses' => 0],
                ['id' => $travel['vienna']->id, 'destination' => 'Vienna', 'first_date' => '2026-12-20', 'last_date' => '2026-12-21', 'client' => 'Globex', 'state' => 'pending', 'expense_total' => null, 'counted_expenses' => 0, 'declined_expenses' => 0],
            ])
            ->where('travelRequests.past', [
                ['id' => $travel['paris']->id, 'destination' => 'Paris', 'first_date' => '2026-12-18', 'last_date' => '2026-12-18', 'client' => null, 'state' => 'cancelled', 'expense_total' => "RON\u{a0}0.00", 'counted_expenses' => 0, 'declined_expenses' => 0],
                ['id' => $travel['munich']->id, 'destination' => 'Munich', 'first_date' => '2026-12-15', 'last_date' => '2026-12-15', 'client' => null, 'state' => 'declined', 'expense_total' => null, 'counted_expenses' => 0, 'declined_expenses' => 0],
                ['id' => $travel['berlin']->id, 'destination' => 'Berlin', 'first_date' => '2026-12-03', 'last_date' => '2026-12-05', 'client' => null, 'state' => 'approved', 'expense_total' => "RON\u{a0}1,990.00", 'counted_expenses' => 1, 'declined_expenses' => 1],
            ]));
});

it('moves a Travel Request to Past the day after its last date', function () {
    $cara = Employee::factory()->create();
    $lisbon = carasTravel($cara)['lisbon'];
    travelTo('2026-12-11 09:00');

    actingAs($cara)->get(route('travel-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequests.upcoming.0.destination', 'Rome')
            ->where('travelRequests.past.2.id', $lisbon->id));
});

it('shows Cara\'s travel on her record\'s Travel tab in the same Upcoming and Past rows to Ana with view travel for her Reports', function () {
    [$ana, $cara] = anaWithViewTravelAndCara(Scope::Reports);
    $travel = carasTravel($cara);

    actingAs($ana)->get(route('employees.show', ['employee' => $cara, 'tab' => 'travel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'travel')
            ->where('tabs', ['overview', 'travel'])
            ->where('travelRequests.upcoming', fn (Collection $rows): bool => $rows->pluck('id')->all() === [$travel['lisbon']->id, $travel['rome']->id, $travel['vienna']->id])
            ->where('travelRequests.past', fn (Collection $rows): bool => $rows->pluck('id')->all() === [$travel['paris']->id, $travel['munich']->id, $travel['berlin']->id])
            ->where('travelRequests.past.2.expense_total', "RON\u{a0}1,990.00")
            ->where('travelRequests.past.2.declined_expenses', 1));
});

it('shows Cara\'s Travel Request page in full, with its Travel Expenses and nothing to do, and breadcrumbs to her record, to Ana with view travel for her Reports', function () {
    [$ana, $cara] = anaWithViewTravelAndCara(Scope::Reports);
    $hotel = berlinTripWithHotel($cara);
    $berlin = $hotel->travelRequest;
    $dinner = TravelExpense::factory()->for($berlin)->declined()->create(['paid_on' => '2026-12-05', 'category' => 'meals', 'description' => 'Dinner', 'amount' => 5000, 'decline_reason' => 'Company card']);

    actingAs($ana)->get(route('travel-requests.show', $berlin))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/Show')
            ->where('travelRequest', [
                'id' => $berlin->id, 'project' => 'Relaunch', 'client' => null, 'destination' => 'Berlin',
                'first_date' => '2026-12-03', 'last_date' => '2026-12-05', 'days' => 3, 'purpose' => 'Checkout workshop',
                'state' => 'approved', 'decided_by' => $berlin->decider?->name, 'decline_reason' => null, 'can_cancel' => false,
                'expenses' => [
                    'items' => [
                        ['id' => $hotel->id, 'paid_on' => '2026-12-04', 'category' => 'accommodation', 'description' => 'Hotel', 'amount' => "RON\u{a0}1,990.00", 'state' => 'pending', 'decline_reason' => null, 'can_change' => false],
                        ['id' => $dinner->id, 'paid_on' => '2026-12-05', 'category' => 'meals', 'description' => 'Dinner', 'amount' => "RON\u{a0}50.00", 'state' => 'declined', 'decline_reason' => 'Company card', 'can_change' => false],
                    ],
                    'total' => "RON\u{a0}1,990.00",
                    'can_create' => false,
                ],
            ])
            ->where('employee', ['id' => $cara->id, 'name' => 'Cara'])
            ->where('isOwn', false)
            ->where('canViewEmployee', true));
    actingAs($ana)->get(route('travel-expenses.receipt.show', $hotel))->assertOk()->assertStreamedContent('receipt');
});

it('shows a Declined Travel Request\'s page with who declined it and why', function () {
    [$ana, $cara] = anaWithViewTravelAndCara(Scope::Reports);
    $munich = TravelRequest::factory()->on(assignmentOf($cara))->declined()->create(['decline_reason' => 'Release week']);

    actingAs($ana)->get(route('travel-requests.show', $munich))
        ->assertInertia(fn (Assert $page) => $page
            ->where('travelRequest.state', 'declined')
            ->where('travelRequest.decided_by', $munich->decider?->name)
            ->where('travelRequest.decline_reason', 'Release week')
            ->where('travelRequest.expenses', null));
});

it('shows the Employee their own Travel Request page, with what they may do and breadcrumbs to their Travel page', function () {
    $cara = Employee::factory()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara']);
    $travel = carasTravel($cara);
    $hotel = $travel['berlin']->travelExpenses()->where('description', 'Hotel')->sole();

    actingAs($cara)->get(route('travel-requests.show', $travel['berlin']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isOwn', true)
            ->where('travelRequest.can_cancel', false)
            ->where('travelRequest.expenses.can_create', true)
            ->where('travelRequest.expenses.items.0.id', $hotel->id)
            ->where('travelRequest.expenses.items.0.can_change', true));
    actingAs($cara)->get(route('travel-requests.show', $travel['rome']))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequest.can_cancel', true));
    actingAs($cara)->get(route('travel-requests.show', $travel['munich']))
        ->assertOk();
});

it('shows a Pending Travel Request\'s page, with nothing to do, to the Employee\'s effective Manager, who may decide it, and refuses them one already decided', function () {
    $mona = Employee::factory()->create();
    $cara = Employee::factory()->reportingTo($mona)->create();
    $travel = carasTravel($cara);

    actingAs($mona)->get(route('travel-requests.show', $travel['vienna']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isOwn', false)
            ->where('travelRequest.can_cancel', false));
    actingAs($mona)->get(route('travel-requests.show', $travel['rome']))->assertForbidden();
});

it('shows a Travel Request page, with nothing to do, to someone who may reopen Travel Expenses', function () {
    $rome = carasTravel(Employee::factory()->create())['rome'];

    actingAs(employeeWith([Permission::ReopenTravelExpenses->value => Scope::Everyone]))->get(route('travel-requests.show', $rome))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isOwn', false)
            ->where('travelRequest.can_cancel', false)
            ->where('travelRequest.expenses.can_create', false));
});

it('refuses Ana with view travel for her Reports the page, Travel Request pages and receipts of someone who isn\'t her Report', function () {
    [$ana] = anaWithViewTravelAndCara(Scope::Reports);
    $outsider = Employee::factory()->create();
    $hotel = berlinTripWithHotel($outsider);

    actingAs($ana)->get(route('employees.show', ['employee' => $outsider, 'tab' => 'travel']))->assertForbidden();
    actingAs($ana)->get(route('travel-requests.show', $hotel->travelRequest))->assertForbidden();
    actingAs($ana)->get(route('travel-expenses.receipt.show', $hotel))->assertForbidden();
});

it('refuses an Employee someone else\'s Travel Request page', function () {
    $hotel = berlinTripWithHotel(Employee::factory()->create());

    actingAs(Employee::factory()->create())->get(route('travel-requests.show', $hotel->travelRequest))->assertForbidden();
});

it('shows anyone\'s travel, Travel Request pages and receipts to Ana with view travel for everyone', function () {
    [$ana] = anaWithViewTravelAndCara(Scope::Everyone);
    $outsider = Employee::factory()->create();
    $hotel = berlinTripWithHotel($outsider);

    actingAs($ana)->get(route('employees.show', ['employee' => $outsider, 'tab' => 'travel']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'travel')->has('travelRequests.past', 1));
    actingAs($ana)->get(route('travel-requests.show', $hotel->travelRequest))->assertOk();
    actingAs($ana)->get(route('travel-expenses.receipt.show', $hotel))->assertOk();
});

it('offers no Travel tab and refuses Travel Request pages and receipts to someone who may see Cara\'s record but not her travel', function () {
    $viewer = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $hotel = berlinTripWithHotel($cara = Employee::factory()->create());

    actingAs($viewer)->get(route('employees.show', ['employee' => $cara, 'tab' => 'travel']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'overview')->where('tabs', ['overview'])->missing('travelRequests'));
    actingAs($viewer)->get(route('travel-requests.show', $hotel->travelRequest))->assertForbidden();
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
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'travel')->has('travelRequests.past', 1));
});
