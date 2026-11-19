<?php

use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Rules\TimeOffFits;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(TimeOffFits::class, EmployeeRequest::class);

beforeEach(function () {
    travelTo('2026-12-01 09:00');
});

/**
 * Cara, who has a Manager, with Time Off from 22 to 24 Dec 2026, Pending
 * unless given another factory state.
 */
function caraWithTimeOffInDecember(?string $state = null): Employee
{
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara', 'manager_id' => Employee::factory()->startedLongAgo()->create()->id]);
    $factory = TimeOff::factory()->for($cara);
    ($state === null ? $factory : $factory->{$state}())->create(['first_date' => '2026-12-22', 'last_date' => '2026-12-24']);

    return $cara;
}

it('refuses a Leaving Date before the last day of Pending or Approved Time Off, and allows one on it', function (?string $state) {
    $cara = caraWithTimeOffInDecember($state);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-12-15']))
        ->assertInvalid(['leaving_date' => 'Cara has Time Off from 22 Dec 2026 to 24 Dec 2026, so their Leaving Date can\'t be earlier than 24 Dec 2026.']);

    expect($cara->refresh()->leaving_date)->toBeNull();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['leaving_date' => '2026-12-24']))
        ->assertValid();

    expect($cara->refresh()->leaving_date?->toDateString())->toBe('2026-12-24');
})->with(['pending' => null, 'approved' => 'approved']);

it('refuses a Start Date after the first day of Pending or Approved Time Off, and allows one on it', function (?string $state) {
    $cara = caraWithTimeOffInDecember($state);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-12-23']))
        ->assertInvalid(['start_date' => 'Cara has Time Off from 22 Dec 2026 to 24 Dec 2026, so their Start Date can\'t be later than 22 Dec 2026.']);

    expect($cara->refresh()->start_date->toDateString())->toBe('2025-01-06');

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-12-22']))
        ->assertValid();

    expect($cara->refresh()->start_date->toDateString())->toBe('2026-12-22');
})->with(['pending' => null, 'approved' => 'approved']);

it('lets Declined or Cancelled Time Off fall outside the employment', function (string $state) {
    $cara = caraWithTimeOffInDecember($state);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara, ['start_date' => '2026-12-23']))
        ->assertValid();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), employeeRecordChanging($cara->refresh(), ['leaving_date' => '2026-12-23']))
        ->assertValid();

    expect($cara->refresh())
        ->start_date->toDateString()->toBe('2026-12-23')
        ->leaving_date?->toDateString()->toBe('2026-12-23');
})->with(['declined', 'cancelled']);
