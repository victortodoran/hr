<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PaidDaysController;
use App\Http\Requests\PaidDaysRequest;
use App\Models\Employee;
use App\Models\PaidDays;
use App\Models\Role;
use App\Policies\EmployeePolicy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(PaidDaysController::class, PaidDaysRequest::class, PaidDays::class, EmployeeController::class, EmployeePolicy::class, Employee::class, Permission::class);

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
 */
function cara(): Employee
{
    return Employee::factory()->create(['name' => 'Cara', 'start_date' => '2025-01-06']);
}

/**
 * Cara's Paid Days for each year, as stored.
 *
 * @return array<int, int>
 */
function storedPaidDays(Employee $employee): array
{
    return PaidDays::query()->where('employee_id', $employee->id)->orderBy('year')->pluck('days', 'year')->all();
}

it('lets someone who manages Paid Days set an Employee\'s Paid Days for a year, leaving other years alone', function () {
    $cara = cara();
    PaidDays::factory()->for($cara)->create(['year' => 2025, 'days' => 18]);
    PaidDays::factory()->for($cara)->create(['year' => 2027, 'days' => 25]);

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['year' => 2026, 'days' => 20])
        ->assertRedirect(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Paid Days for 2026 saved.']);

    expect(storedPaidDays($cara))->toBe([2025 => 18, 2026 => 20, 2027 => 25]);
});

it('changes a year\'s Paid Days that are already set', function () {
    $cara = cara();
    PaidDays::factory()->for($cara)->create(['year' => 2026, 'days' => 20]);

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['year' => 2026, 'days' => 8])
        ->assertSessionHasNoErrors();

    expect(storedPaidDays($cara))->toBe([2026 => 8]);
});

it('allows 0 Paid Days and next year\'s', function (int $year, int $days) {
    $cara = cara();

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['year' => $year, 'days' => $days])
        ->assertSessionHasNoErrors();

    expect(storedPaidDays($cara))->toBe([$year => $days]);
})->with([
    'none this year' => [2026, 0],
    'next year' => [2027, 22],
]);

it('shows Paid Days for this year and next, a year with no number copying the most recent earlier one', function (array $set, array $shown) {
    $cara = cara();
    foreach ($set as $year => $days) {
        PaidDays::factory()->for($cara)->create(['year' => $year, 'days' => $days]);
    }

    actingAs(paidDaysManager())->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paidDays', [
                ['year' => 2026, 'days' => $shown[0]],
                ['year' => 2027, 'days' => $shown[1]],
            ]));
})->with([
    'nothing set at all' => [[], [0, 0]],
    'this year copied forward' => [[2026 => 20], [20, 20]],
    'an earlier year copied forward' => [[2024 => 15, 2025 => 18], [18, 18]],
    'each year its own' => [[2026 => 8, 2027 => 20], [8, 20]],
    'a later year never copied back' => [[2027 => 20], [0, 20]],
    'a year far ahead ignored' => [[2025 => 18, 2030 => 30], [18, 18]],
]);

it('keeps copying forward into the new year', function () {
    $cara = cara();
    PaidDays::factory()->for($cara)->create(['year' => 2026, 'days' => 20]);
    PaidDays::factory()->for(Employee::factory())->create(['year' => 2027, 'days' => 5]);
    travelTo('2027-01-01 09:00');

    actingAs(paidDaysManager())->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paidDays', [
                ['year' => 2027, 'days' => 20],
                ['year' => 2028, 'days' => 20],
            ]));
});

it('shows Paid Days only to those who manage them for the Employee', function () {
    $cara = cara();

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]))
        ->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'overview')->missing('paidDays'));
});

it('lets someone who only manages Paid Days open the Employee\'s page to set them', function () {
    $cara = cara();

    actingAs(paidDaysManager())->get(route('employees.show', ['employee' => $cara, 'tab' => 'time-off']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tabs', ['overview', 'time-off'])
            ->where('tab', 'time-off')
            ->has('paidDays', 2));
});

it('refuses setting Paid Days to someone without Manage Paid Days for the Employee', function (array $grants) {
    $cara = cara();

    actingAs(employeeWith($grants))
        ->post(route('employees.paid-days.store', $cara), ['year' => 2026, 'days' => 20])
        ->assertForbidden();

    expect(storedPaidDays($cara))->toBe([]);
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
    $dana = Employee::factory()->top()->create();
    $ben = employeeWith([Permission::ManagePaidDays->value => Scope::Reports], ['manager_id' => $dana->id]);
    $cara = Employee::factory()->create(['manager_id' => $ben->id]);
    $dev = Employee::factory()->create(['manager_id' => $cara->id]);
    $eve = Employee::factory()->create(['manager_id' => $dana->id]);

    foreach ([$cara, $dev] as $report) {
        actingAs($ben)->post(route('employees.paid-days.store', $report), ['year' => 2026, 'days' => 20])->assertSessionHasNoErrors();
    }
    foreach ([$ben, $dana, $eve] as $other) {
        actingAs($ben)->post(route('employees.paid-days.store', $other), ['year' => 2026, 'days' => 20])->assertForbidden();
    }

    expect(PaidDays::query()->pluck('employee_id')->sort()->values()->all())->toBe([$cara->id, $dev->id]);
});

it('lets Admin set Paid Days without being granted Manage Paid Days', function () {
    $cara = cara();
    $ana = Employee::factory()->create();
    $ana->roles()->attach(Role::factory()->admin()->create());

    actingAs($ana)
        ->post(route('employees.paid-days.store', $cara), ['year' => 2026, 'days' => 20])
        ->assertSessionHasNoErrors();

    expect(storedPaidDays($cara))->toBe([2026 => 20]);
});

it('refuses Paid Days that are negative, fractions or too many, and years already past', function (array $input, string $field, string $message) {
    $cara = cara();

    actingAs(paidDaysManager())
        ->post(route('employees.paid-days.store', $cara), ['year' => 2026, 'days' => 20, ...$input])
        ->assertSessionHasErrors([$field => $message]);

    expect(storedPaidDays($cara))->toBe([]);
})->with([
    'no days' => [['days' => ''], 'days', 'The Paid Days field is required.'],
    'negative' => [['days' => -1], 'days', 'The Paid Days field must be at least 0.'],
    'a fraction' => [['days' => '2.5'], 'days', 'The Paid Days field must be an integer.'],
    'more days than a year has' => [['days' => 367], 'days', 'The Paid Days field must not be greater than 366.'],
    'no year' => [['year' => ''], 'year', 'The year field is required.'],
    'not a year' => [['year' => 'next'], 'year', 'The year field must be an integer.'],
    'last year' => [['year' => 2025], 'year', 'Paid Days can only be set from 2026 onward.'],
    'too far ahead' => [['year' => 10000], 'year', 'The year field must not be greater than 9999.'],
]);
