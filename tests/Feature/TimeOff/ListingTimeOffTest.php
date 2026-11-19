<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\TimeOff\TimeOffListController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\TimeOff\TimeOffListRequest;
use App\Models\Builders\TimeOffBuilder;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Pages\TimeOff\TimeOffListPage;
use App\Policies\TimeOffPolicy;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffListController::class, TimeOffListRequest::class, TimeOffListPage::class, TimeOffBuilder::class, TimeOffPolicy::class, HandleInertiaRequests::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

/**
 * Hana, who may view the Time Off of everyone (or the given grants instead).
 *
 * @param  array<string, Scope|null>|null  $grants
 */
function timeOffViewer(?array $grants = null): Employee
{
    return employeeWith($grants ?? [Permission::ViewTimeOff->value => Scope::Everyone], ['name' => 'Hana']);
}

/**
 * Pending Paid Time Off of the Employee's, from the first to the last date.
 */
function timeOffOf(Employee $employee, string $first, string $last): TimeOff
{
    return TimeOff::factory()->for($employee)->create(['first_date' => $first, 'last_date' => $last]);
}

/**
 * Matches a page of listed Time Off that is these, in this order.
 *
 * @param  list<int>  $ids
 * @return Closure(Collection<int, array{id: int}>): bool
 */
function timeOffListing(array $ids): Closure
{
    return fn (Collection $rows): bool => $rows->pluck('id')->all() === $ids;
}

it('lists everyone\'s Time Off with a date in the current month, by first date, with its weekdays and whether it is Paid', function () {
    $hana = timeOffViewer();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $dan = Employee::factory()->create(['name' => 'Dan']);
    $christmas = TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-12-22', 'last_date' => '2026-12-24']);
    $newYear = TimeOff::factory()->for($dan)->unpaid()->create(['first_date' => '2026-12-29', 'last_date' => '2027-01-04']);
    $november = timeOffOf($dan, '2026-11-30', '2026-12-01');
    timeOffOf($cara, '2026-11-09', '2026-11-10');

    actingAs($hana)->get(route('time-off-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('time-off/List')
            ->where('filters', ['period' => ['mode' => 'month', 'from' => '2026-12-01', 'to' => '2026-12-31', 'query' => ['month' => '2026-12']], 'employee' => 'all', 'state' => null])
            ->where('timeOff.data', [
                ['id' => $november->id, 'employee' => ['id' => $dan->id, 'name' => 'Dan'], 'first_date' => '2026-11-30', 'last_date' => '2026-12-01', 'days' => 2, 'is_paid' => true, 'state' => 'pending'],
                ['id' => $christmas->id, 'employee' => ['id' => $cara->id, 'name' => 'Cara'], 'first_date' => '2026-12-22', 'last_date' => '2026-12-24', 'days' => 3, 'is_paid' => true, 'state' => 'approved'],
                ['id' => $newYear->id, 'employee' => ['id' => $dan->id, 'name' => 'Dan'], 'first_date' => '2026-12-29', 'last_date' => '2027-01-04', 'days' => 5, 'is_paid' => false, 'state' => 'pending'],
            ])
            ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 1], ['id' => $dan->id, 'name' => 'Dan', 'count' => 2]]));
});

it('lists Time Off in a future month it has a date in', function () {
    $newYear = timeOffOf(Employee::factory()->create(), '2026-12-29', '2027-01-04');

    actingAs(timeOffViewer())->get(route('time-off-list.index', ['month' => '2027-01']))
        ->assertInertia(fn (Assert $page) => $page->where('timeOff.data', timeOffListing([$newYear->id])));
});

it('narrows the list to one Employee and one state', function () {
    $hana = timeOffViewer();
    $cara = Employee::factory()->create(['name' => 'Cara']);
    $pending = timeOffOf($cara, '2026-12-07', '2026-12-08');
    $declined = TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-12-14', 'last_date' => '2026-12-14']);
    timeOffOf(Employee::factory()->create(), '2026-12-09', '2026-12-09');

    actingAs($hana)->get(route('time-off-list.index', ['employee' => $cara->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', $cara->id)
            ->where('timeOff.data', timeOffListing([$pending->id, $declined->id])));
    actingAs($hana)->get(route('time-off-list.index', ['employee' => 'all', 'state' => 'declined']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.state', 'declined')
            ->where('timeOff.data', timeOffListing([$declined->id]))
            ->where('employees', [['id' => $cara->id, 'name' => 'Cara', 'count' => 1]]));
});

it('falls back to everyone for an Employee with no Time Off to show', function () {
    $timeOff = timeOffOf(Employee::factory()->create(), '2026-12-07', '2026-12-08');

    actingAs(timeOffViewer())->get(route('time-off-list.index', ['employee' => Employee::factory()->create()->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.employee', 'all')
            ->where('timeOff.data', timeOffListing([$timeOff->id])));
});

it('lists only the Employees a Scope for seeing Time Off covers', function (string $permission) {
    $ben = timeOffViewer([$permission => Scope::Reports]);
    $report = timeOffOf(Employee::factory()->reportingTo($ben)->create(), '2026-12-07', '2026-12-08');
    timeOffOf(Employee::factory()->create(), '2026-12-09', '2026-12-09');

    actingAs($ben)->get(route('time-off-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('timeOff.data', timeOffListing([$report->id])));
    actingAs($ben)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTimeOffList', true));
})->with([Permission::ViewTimeOff->value, Permission::ManagePaidDays->value]);

it('refuses the page, and leaves it out of the menu, for someone who sees only their own Time Off', function () {
    $cara = timeOffViewer([Permission::ViewTimeOff->value => Scope::Own]);

    actingAs($cara)->get(route('time-off-list.index'))->assertForbidden();
    actingAs($cara)->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeTimeOffList', false));
});

it('pages the list 25 at a time', function () {
    $cara = Employee::factory()->create();
    TimeOff::factory()->for($cara)->count(26)->create(['first_date' => '2026-12-07', 'last_date' => '2026-12-07']);

    actingAs(timeOffViewer())->get(route('time-off-list.index'))
        ->assertInertia(fn (Assert $page) => $page->count('timeOff.data', 25)->where('timeOff.total', 26));
});
