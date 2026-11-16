<?php

use App\Enums\TimeOffState;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\TimeOff\TimeOffController;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Policies\WorkEntryPolicy;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

covers(EmployeeController::class, EmployeeRequest::class, TimeOffController::class, WorkEntryController::class, WorkEntryPolicy::class, Employee::class);

beforeEach(fn () => travelTo('2026-12-01 09:00'));

/**
 * The record of an Employee as the edit form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function recordOf(Employee $employee, array $changes = []): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'department_id' => $employee->department_id,
        'manager_id' => $employee->manager_id,
        'start_date' => $employee->start_date->toDateString(),
        'leaving_date' => $employee->leaving_date?->toDateString(),
        ...$changes,
    ];
}

it('lets HR add several Employees with no Manager, such as co-founders', function () {
    Notification::fake();
    Project::factory()->internal()->create();
    $hr = employeeWhoManagesEmployees();
    $department = $hr->department_id;

    foreach (['ana@acme.test', 'dan@acme.test'] as $email) {
        actingAs($hr)->post(route('employees.store'), ['name' => 'Founder', 'email' => $email, 'department_id' => $department, 'manager_id' => null, 'start_date' => '2025-01-06'])
            ->assertSessionHasNoErrors();
    }

    expect(Employee::query()->whereIn('email', ['ana@acme.test', 'dan@acme.test'])->whereNull('manager_id')->count())->toBe(2);
});

it('approves the Time Off of an Employee with no Manager as soon as they request it, and of one whose Managers have all left', function (Closure $employee) {
    $cara = $employee();

    actingAs($cara)->post(route('time-off.store'), ['first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'is_paid' => false])
        ->assertSessionHasNoErrors();

    expect(TimeOff::query()->where('employee_id', $cara->id)->sole()->state)->toBe(TimeOffState::Approved);
})->with([
    'no Manager' => [fn () => Employee::factory()->startedLongAgo()->create()],
    'their only Manager has left' => [fn () => Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->former()->create())->create()],
]);

it('keeps the Time Off of an Employee with a Manager Pending', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create();

    actingAs($cara)->post(route('time-off.store'), ['first_date' => '2026-12-14', 'last_date' => '2026-12-16', 'is_paid' => false]);

    expect(TimeOff::query()->where('employee_id', $cara->id)->sole()->state)->toBe(TimeOffState::Pending);
});

it('refuses removing an Employee\'s Manager while they have Pending Work Entries, Time Off, Travel Requests or Travel Expenses, and allows it once decided', function (Closure $pending) {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara Lane']);
    $item = $pending($cara);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), recordOf($cara, ['manager_id' => null]))
        ->assertSessionHasErrors(['manager_id' => 'Cara Lane has Pending Work Entries, Time Off, Travel Requests or Travel Expenses, which nobody could decide without a Manager. Decide them first.']);

    $item->delete();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), recordOf($cara, ['manager_id' => null]))
        ->assertSessionHasNoErrors();

    expect($cara->refresh()->manager_id)->toBeNull();
})->with([
    'Pending Work Entries' => [fn (Employee $cara) => WorkEntry::factory()->for(assignmentOf($cara))->create()],
    'Pending Time Off' => [fn (Employee $cara) => TimeOff::factory()->for($cara)->create()],
    'a Pending Travel Request' => [fn (Employee $cara) => TravelRequest::factory()->on(assignmentOf($cara))->create()],
    'a Pending Travel Expense' => [fn (Employee $cara) => TravelExpense::factory()->for(TravelRequest::factory()->on(assignmentOf($cara))->approved())->create()],
]);

it('refuses a Leaving Date that would leave a Report with no Manager and Pending items, until they have a new Manager', function (Closure $pending) {
    $ana = Employee::factory()->startedLongAgo()->create(['name' => 'Ana Founder']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ana)->create(['name' => 'Cara Lane']);
    $pending($cara);
    $hr = employeeWhoManagesEmployees();

    actingAs($hr)->patch(route('employees.update', $ana), recordOf($ana, ['leaving_date' => '2026-12-31']))
        ->assertSessionHasErrors(['leaving_date' => 'Cara Lane would have no Manager to decide their Pending Work Entries, Time Off, Travel Requests or Travel Expenses. Give them a new Manager first.']);

    $cara->update(['manager_id' => Employee::factory()->create()->id]);

    actingAs($hr)->patch(route('employees.update', $ana), recordOf($ana, ['leaving_date' => '2026-12-31']))
        ->assertSessionHasNoErrors();
})->with([
    'Pending Time Off' => [fn (Employee $cara) => TimeOff::factory()->for($cara)->create()],
    'a Pending Travel Request' => [fn (Employee $cara) => TravelRequest::factory()->on(assignmentOf($cara))->create()],
    'a Pending Travel Expense' => [fn (Employee $cara) => TravelExpense::factory()->for(TravelRequest::factory()->on(assignmentOf($cara))->approved())->create()],
]);

it('allows a Leaving Date when the next Manager up takes over the Reports', function () {
    $ana = Employee::factory()->startedLongAgo()->create();
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create();
    TimeOff::factory()->for($cara)->create();

    actingAs(employeeWhoManagesEmployees())->patch(route('employees.update', $ben), recordOf($ben, ['leaving_date' => '2026-12-31']))
        ->assertSessionHasNoErrors();
});

it('refuses removing the Manager of a Former Employee when a Report\'s Pending items reach their Manager through them', function () {
    $ana = Employee::factory()->startedLongAgo()->create();
    $ben = Employee::factory()->reportingTo($ana)->create(['start_date' => '2025-01-06', 'leaving_date' => '2026-06-30']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lane']);
    TimeOff::factory()->for($cara)->create();

    actingAs(employeeWhoManagesEmployees())->patch(route('employees.update', $ben), recordOf($ben, ['manager_id' => null]))
        ->assertSessionHasErrors(['manager_id' => 'Cara Lane would have no Manager to decide their Pending Work Entries, Time Off, Travel Requests or Travel Expenses. Give them a new Manager first.']);
});

it('refuses a new Manager who has left with nobody above, while the Employee has Pending items', function () {
    $cara = Employee::factory()->startedLongAgo()->reportingTo(Employee::factory()->create())->create(['name' => 'Cara Lane']);
    TimeOff::factory()->for($cara)->create();
    $gone = Employee::factory()->former()->create();

    actingAs(employeeWhoManagesEmployees())->patch(route('employees.update', $cara), recordOf($cara, ['manager_id' => $gone->id]))
        ->assertSessionHasErrors(['manager_id' => 'Cara Lane has Pending Work Entries, Time Off, Travel Requests or Travel Expenses, which nobody could decide without a Manager. Decide them first.']);
});

it('refuses a Leaving Date after that of the last Manager above, which would leave a Report with nobody', function () {
    $ana = Employee::factory()->startedLongAgo()->create(['leaving_date' => '2026-12-15']);
    $ben = Employee::factory()->startedLongAgo()->reportingTo($ana)->create();
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ben)->create(['name' => 'Cara Lane']);
    TimeOff::factory()->for($cara)->create();

    actingAs(employeeWhoManagesEmployees())->patch(route('employees.update', $ben), recordOf($ben, ['leaving_date' => '2026-12-31']))
        ->assertSessionHasErrors(['leaving_date' => 'Cara Lane would have no Manager to decide their Pending Work Entries, Time Off, Travel Requests or Travel Expenses. Give them a new Manager first.']);
});

it('saves other changes to an Employee whose Leaving Date was accepted before a Report had Pending items', function () {
    $ana = Employee::factory()->startedLongAgo()->create(['leaving_date' => '2026-12-31']);
    $cara = Employee::factory()->startedLongAgo()->reportingTo($ana)->create();
    TimeOff::factory()->for($cara)->create();

    actingAs(employeeWhoManagesEmployees())->patch(route('employees.update', $ana), recordOf($ana, ['name' => 'Ana Founder']))
        ->assertSessionHasNoErrors();

    expect($ana->refresh()->name)->toBe('Ana Founder');
});
