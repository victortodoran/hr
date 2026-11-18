<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Travel\TravelRequestListController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\Travel\TravelRequestListRequest;
use App\Models\Builders\TravelRequestBuilder;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Policies\TravelRequestPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TravelRequestListController::class, TravelRequestListRequest::class, TravelRequestBuilder::class, TravelRequestPolicy::class, HandleInertiaRequests::class);

beforeEach(function () {
    travelTo('2026-11-18 09:00');
    CompanySettings::factory()->create(['currency' => 'RON']);
});

/**
 * Dana, who may view the travel of everyone (or the given grants instead).
 *
 * @param  array<string, Scope|null>|null  $grants
 */
function travelViewer(?array $grants = null): Employee
{
    return employeeWith($grants ?? [Permission::ViewTravel->value => Scope::Everyone], ['name' => 'Dana']);
}

/**
 * An Approved Travel Request of the Employee's on their Assignment to the
 * Project, from the first to the last date.
 */
function tripOf(Employee $employee, string $destination, string $first, string $last, string $project = 'Relaunch'): TravelRequest
{
    return TravelRequest::factory()->on(assignmentOf($employee, $project))->approved()
        ->create(['destination' => $destination, 'first_date' => $first, 'last_date' => $last]);
}

/**
 * Matches a page of listed Travel Requests that are these, in this order.
 *
 * @param  list<int>  $ids
 * @return Closure(Collection<int, array{id: int}>): bool
 */
function listing(array $ids): Closure
{
    return fn (Collection $rows): bool => $rows->pluck('id')->all() === $ids;
}

it('lists everyone\'s Travel Requests with a day in the current month, by first date, with what was spent on each', function () {
    $dana = travelViewer();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $lisbon = tripOf($cara, 'Lisbon', '2026-11-30', '2026-12-02');
    TravelExpense::factory()->for($lisbon)->approved()->create(['amount' => 90000, 'paid_on' => '2026-11-15']);
    TravelExpense::factory()->for($lisbon)->create(['amount' => 10000, 'paid_on' => '2026-11-16']);
    TravelExpense::factory()->for($lisbon)->declined()->create(['amount' => 50000, 'paid_on' => '2026-11-16']);
    $paris = TravelRequest::factory()->on(assignmentOf($ben, 'Atlas'))->create(['destination' => 'Paris', 'first_date' => '2026-11-20', 'last_date' => '2026-11-21']);
    tripOf($ben, 'Rome', '2026-10-05', '2026-10-06');

    actingAs($dana)->get(route('travel-request-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('travel-requests/List')
            ->where('filters', ['period' => ['mode' => 'month', 'from' => '2026-11-01', 'to' => '2026-11-30', 'query' => ['month' => '2026-11']], 'employee' => 'all', 'project' => null, 'state' => null])
            ->where('travelRequests.data', [
                ['id' => $paris->id, 'employee' => ['id' => $ben->id, 'name' => 'Ben'], 'project' => 'Atlas', 'destination' => 'Paris', 'first_date' => '2026-11-20', 'last_date' => '2026-11-21', 'state' => 'pending', 'expenses' => null],
                ['id' => $lisbon->id, 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'project' => 'Relaunch', 'destination' => 'Lisbon', 'first_date' => '2026-11-30', 'last_date' => '2026-12-02', 'state' => 'approved', 'expenses' => ['total' => "RON\u{a0}1,000.00", 'pending' => 1]],
            ])
            ->where('total', "RON\u{a0}1,000.00")
            ->where('employees', [['id' => $ben->id, 'name' => 'Ben', 'count' => 1], ['id' => $cara->id, 'name' => 'Cara', 'count' => 1]]));
});

it('lists a Travel Request in every month it has a day in, counting its expenses in each month\'s total, future months included', function () {
    $dana = travelViewer();
    $lisbon = tripOf(Employee::factory()->create(), 'Lisbon', '2026-11-30', '2026-12-02');
    TravelExpense::factory()->for($lisbon)->create(['amount' => 90000, 'paid_on' => '2026-11-15']);

    foreach (['2026-11', '2026-12'] as $month) {
        actingAs($dana)->get(route('travel-request-list.index', ['month' => $month]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period.query', ['month' => $month])
                ->where('travelRequests.data.0.id', $lisbon->id)
                ->where('total', "RON\u{a0}900.00"));
    }
    actingAs($dana)->get(route('travel-request-list.index', ['month' => '2027-01']))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequests.data', [])->where('total', "RON\u{a0}0.00"));
});

it('narrows the list to one Employee, one Project and one state', function () {
    $dana = travelViewer();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $berlin = tripOf($cara, 'Berlin', '2026-11-03', '2026-11-05');
    $atlas = tripOf($cara, 'Oslo', '2026-11-10', '2026-11-11', 'Atlas');
    $cancelled = TravelRequest::factory()->on($berlin->assignment)->cancelled()->create(['destination' => 'Vienna', 'first_date' => '2026-11-24', 'last_date' => '2026-11-25']);
    tripOf(Employee::factory()->create(), 'Madrid', '2026-11-12', '2026-11-13');

    actingAs($dana)->get(route('travel-request-list.index', ['employee' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', $cara->id)
            ->where('travelRequests.data', listing([$berlin->id, $atlas->id, $cancelled->id]))
            ->where('projects', [['id' => $atlas->assignment->project_id, 'name' => 'Atlas'], ['id' => $berlin->assignment->project_id, 'name' => 'Relaunch']]));
    actingAs($dana)->get(route('travel-request-list.index', ['employee' => $cara->id, 'project' => $atlas->assignment->project_id]))
        ->assertInertia(fn (Assert $page) => $page->where('travelRequests.data', listing([$atlas->id])));
    actingAs($dana)->get(route('travel-request-list.index', ['employee' => 'all', 'state' => 'cancelled']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.state', 'cancelled')
            ->where('travelRequests.data', listing([$cancelled->id])));
});

it('falls back to everyone for an Employee with no Travel Requests to show', function () {
    $dana = travelViewer();
    $trip = tripOf(Employee::factory()->create(), 'Berlin', '2026-11-03', '2026-11-05');

    actingAs($dana)->get(route('travel-request-list.index', ['employee' => Employee::factory()->create()->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', 'all')
            ->where('travelRequests.data', listing([$trip->id])));
});

it('lists only the Employees a reopen Scope covers', function () {
    $rae = travelViewer([Permission::ReopenTravelExpenses->value => Scope::Reports]);
    $report = tripOf(Employee::factory()->reportingTo($rae)->create(), 'Berlin', '2026-11-03', '2026-11-05');
    tripOf(Employee::factory()->create(), 'Madrid', '2026-11-12', '2026-11-13');

    actingAs($rae)->get(route('travel-request-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('travelRequests.data', listing([$report->id])));
});

it('refuses the page, and leaves it out of the menu, for a Manager without a travel Permission', function () {
    $ben = managerBen();
    tripOf(Employee::factory()->reportingTo($ben)->create(), 'Berlin', '2026-11-03', '2026-11-05');

    actingAs($ben)->get(route('travel-request-list.index'))->assertForbidden();
    actingAs($ben)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTravelRequestList', false));
});

it('shows the page in the menu to those who view travel or reopen Travel Expenses', function (string $permission) {
    actingAs(travelViewer([$permission => Scope::Reports]))->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTravelRequestList', true));
})->with([Permission::ViewTravel->value, Permission::ReopenTravelExpenses->value]);
