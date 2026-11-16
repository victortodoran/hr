<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TimeOffController;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Policies\EmployeePolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeePolicy::class, TimeOffController::class, TimeOff::class, Permission::class);

beforeEach(function () {
    travelTo('2026-03-04 09:00');
});

/**
 * Cara, who reports to Ben, with 20 Paid Days a year and Time Off in
 * every state: 5 days of Approved Paid Time Off already taken, Declined
 * Paid Time Off, 2 days of Pending Paid Time Off and Cancelled Unpaid
 * Time Off, all decided by Ben.
 */
function caraWithTimeOff(Employee $ben): Employee
{
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara', 'paid_days' => 20]);

    TimeOff::factory()->for($cara)->approved($ben)->create(['first_date' => '2026-02-16', 'last_date' => '2026-02-20']);
    TimeOff::factory()->for($cara)->declined()->create(['first_date' => '2026-04-06', 'last_date' => '2026-04-07', 'decided_by' => $ben->id, 'decline_reason' => 'Release week']);
    TimeOff::factory()->for($cara)->create(['first_date' => '2026-05-04', 'last_date' => '2026-05-05']);
    TimeOff::factory()->for($cara)->unpaid()->cancelled()->create(['first_date' => '2026-06-01', 'last_date' => '2026-06-05']);

    return $cara;
}

it('shows Cara\'s Paid Days, Paid Days left and Time Off, latest first, on her record to someone with View Time Off for her', function () {
    $ben = Employee::factory()->create(['name' => 'Ben']);
    $cara = caraWithTimeOff($ben);
    $ids = $cara->timeOff()->orderByDesc('first_date')->pluck('id')->all();

    actingAs(employeeWith([Permission::ViewTimeOff->value => Scope::Everyone]))
        ->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('paidDays', null)
            ->where('timeOff', [
                'year' => 2026,
                'paid_days' => 20,
                'paid_days_left' => 13,
                'requests' => [
                    ['id' => $ids[0], 'first_date' => '2026-06-01', 'last_date' => '2026-06-05', 'days' => 5, 'is_paid' => false, 'state' => 'cancelled', 'decided_by' => null, 'decline_reason' => null],
                    ['id' => $ids[1], 'first_date' => '2026-05-04', 'last_date' => '2026-05-05', 'days' => 2, 'is_paid' => true, 'state' => 'pending', 'decided_by' => null, 'decline_reason' => null],
                    ['id' => $ids[2], 'first_date' => '2026-04-06', 'last_date' => '2026-04-07', 'days' => 2, 'is_paid' => true, 'state' => 'declined', 'decided_by' => 'Ben', 'decline_reason' => 'Release week'],
                    ['id' => $ids[3], 'first_date' => '2026-02-16', 'last_date' => '2026-02-20', 'days' => 5, 'is_paid' => true, 'state' => 'approved', 'decided_by' => 'Ben', 'decline_reason' => null],
                ],
            ]));
});

it('shows no Time Off to someone who may see Cara\'s record but not her Time Off', function () {
    $cara = caraWithTimeOff(Employee::factory()->create());

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))
        ->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'overview')->missing('timeOff'));
});

it('refuses Cara\'s page to someone with View Time Off only for themselves', function () {
    $cara = caraWithTimeOff(Employee::factory()->create());

    actingAs(employeeWith([Permission::ViewTimeOff->value => Scope::Own]))
        ->get(route('employees.show', $cara))
        ->assertForbidden();
});

it('lets an Employee with View Time Off for themselves see their own Time Off on their record', function () {
    $cara = caraWithTimeOff(Employee::factory()->create());
    giveRole($cara, [Permission::ViewTimeOff->value => Scope::Own]);

    actingAs($cara)->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page->where('timeOff.paid_days_left', 13)->has('timeOff.requests', 4));
});

it('shows Cara\'s Time Off to someone who manages her Paid Days', function () {
    $cara = caraWithTimeOff(Employee::factory()->create());

    actingAs(employeeWith([Permission::ManagePaidDays->value => Scope::Everyone]))
        ->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paidDays', 20)
            ->where('timeOff.paid_days', 20)
            ->has('timeOff.requests', 4));
});

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lets someone with the reports Scope see the Time Off of their Reports only', function () {
    $dana = Employee::factory()->top()->create();
    $ben = employeeWith([Permission::ViewTimeOff->value => Scope::Reports], ['manager_id' => $dana->id]);
    $cara = Employee::factory()->reportingTo($ben)->create();
    $people = [
        'Dana' => $dana,
        'Ben' => $ben,
        'Cara' => $cara,
        'Dev' => Employee::factory()->reportingTo($cara)->create(),
        'Eve' => Employee::factory()->reportingTo($dana)->create(),
    ];

    foreach ($people as $name => $employee) {
        actingAs($ben)->get(route('employees.show', $employee))
            ->assertStatus(in_array($name, ['Cara', 'Dev'], true) ? 200 : 403);
    }
});
