<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\TimeOff\PaidDaysController;
use App\Http\Requests\TimeOff\PaidDaysRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TimeOff;
use App\Policies\EmployeePolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PaidDaysController::class, PaidDaysRequest::class, EmployeeController::class, EmployeePolicy::class, Employee::class, Permission::class);

beforeEach(function () {
    travelTo('2026-03-01 09:00');
});

/**
 * An Employee who may manage everyone's Paid Days, such as someone in HR.
 */
function paidDaysManager(): Employee
{
    return employeeWith([Permission::ManagePaidDays->value => Scope::Everyone]);
}

/**
 * An Employee named Cara.
 *
 * @param  array<string, mixed>  $attributes
 */
function cara(array $attributes = []): Employee
{
    return Employee::factory()->startedLongAgo()->create(['name' => 'Cara', ...$attributes]);
}

/**
 * The Paid Days the Employee's page shows on its Time Off tab to someone who
 * manages them.
 */
function shownPaidDays(Employee $employee): mixed
{
    return actingAs(paidDaysManager())
        ->get(route('employees.show', ['employee' => $employee, 'tab' => 'time-off']))
        ->inertiaProps('paidDays');
}

it('lets someone who manages Paid Days set an Employee\'s Paid Days, with no year', function () {
    $cara = cara();

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['days' => 20])
        ->assertRedirect(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Paid Days saved.']);

    expect(shownPaidDays($cara))->toBe(20);
});

it('gives a new Employee no Paid Days', function () {
    expect(shownPaidDays(cara()))->toBe(0);
});

it('allows 0 Paid Days and as many as a leap year has', function (int $days) {
    $cara = cara(['paid_days' => 20]);

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['days' => $days])
        ->assertSessionHasNoErrors();

    expect(shownPaidDays($cara))->toBe($days);
})->with([0, 366]);

it('saves Paid Days lowered below what is booked, leaving the booked Time Off as it is and days left negative', function () {
    $cara = cara(['paid_days' => 20]);
    $booked = TimeOff::factory()->for($cara)->approved()->create(['first_date' => '2026-02-02', 'last_date' => '2026-02-20']);

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['days' => 10])
        ->assertSessionHasNoErrors();

    actingAs(paidDaysManager())->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paidDays', 10)
            ->where('timeOff.paid_days', 10)
            ->where('timeOff.paid_days_left', -5)
            ->where('timeOff.requests.0.id', $booked->id)
            ->where('timeOff.requests.0.state', 'approved')
            ->where('timeOff.requests.0.days', 15));
});

it('refuses setting Paid Days to someone without Manage Paid Days for the Employee', function (array $grants) {
    $cara = cara(['paid_days' => 20]);

    actingAs(employeeWith($grants))
        ->post(route('employees.paid-days.store', $cara), ['days' => 5])
        ->assertForbidden();

    expect(shownPaidDays($cara))->toBe(20);
})->with([
    'no Permission' => [[]],
    'managing Employees and Pay' => [[Permission::ManageEmployees->value => null, Permission::ManagePay->value => Scope::Everyone]],
    'Manage Paid Days for themselves only' => [[Permission::ManagePaidDays->value => Scope::Own]],
]);

/*
 * Dana (top)
 * ├── Ben ── Cara ── Dev
 * └── Eve
 */
it('lets someone with the reports Scope set Paid Days for their Reports only', function () {
    $dana = Employee::factory()->create();
    $ben = employeeWith([Permission::ManagePaidDays->value => Scope::Reports], ['manager_id' => $dana->id]);
    $dev = Employee::factory()->reportingTo(Employee::factory()->reportingTo($ben)->create())->create();
    $eve = Employee::factory()->reportingTo($dana)->create();

    actingAs($ben)->post(route('employees.paid-days.store', $dev), ['days' => 20])->assertSessionHasNoErrors();
    foreach ([$ben, $eve] as $other) {
        actingAs($ben)->post(route('employees.paid-days.store', $other), ['days' => 20])->assertForbidden();
    }

    expect(array_map(shownPaidDays(...), [$dev, $ben, $eve]))->toBe([20, 0, 0]);
});

it('lets Admin set Paid Days without being granted Manage Paid Days', function () {
    $cara = cara();
    $ana = Employee::factory()->create();
    $ana->roles()->attach(Role::factory()->admin()->create());

    actingAs($ana)
        ->post(route('employees.paid-days.store', $cara), ['days' => 20])
        ->assertSessionHasNoErrors();

    expect(shownPaidDays($cara))->toBe(20);
});

it('refuses Paid Days that are missing, negative, fractions or too many', function (mixed $days, string $message) {
    $cara = cara(['paid_days' => 20]);

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['days' => $days])
        ->assertSessionHasErrors(['days' => $message]);

    expect(shownPaidDays($cara))->toBe(20);
})->with([
    'no days' => ['', 'The Paid Days field is required.'],
    'negative' => [-1, 'The Paid Days field must be at least 0.'],
    'a fraction' => ['2.5', 'The Paid Days field must be an integer.'],
    'more days than a year has' => [367, 'The Paid Days field must not be greater than 366.'],
]);
