<?php

use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(EmployeeRequest::class, Employee::class);

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function managerChange(Employee $employee, array $changes): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'department_id' => $employee->department_id,
        'manager_id' => $employee->manager_id,
        'start_date' => $employee->start_date->toDateString(),
        ...$changes,
    ];
}

/*
 * Dana (top) ── Ben ── Cara ── Dev
 */
it('refuses a Manager change that would create a loop', function (string $newManager) {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->reportingTo($dana)->create();
    $cara = Employee::factory()->reportingTo($ben)->create();
    $dev = Employee::factory()->reportingTo($cara)->create();
    $people = ['Cara' => $cara, 'Dev' => $dev];

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $ben), managerChange($ben, ['manager_id' => $people[$newManager]->id]))
        ->assertSessionHasErrors(['manager_id' => 'That would make a loop: the new Manager reports to this Employee.']);

    expect($ben->refresh()->manager_id)->toBe($dana->id);
})->with(['a direct Report' => 'Cara', 'an indirect Report' => 'Dev']);

it('allows moving an Employee under someone outside their own reporting line', function () {
    $dana = Employee::factory()->top()->create();
    $ben = Employee::factory()->reportingTo($dana)->create();
    $eve = Employee::factory()->reportingTo($dana)->create();
    $cara = Employee::factory()->reportingTo($ben)->create();

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $cara), managerChange($cara, ['manager_id' => $eve->id]))
        ->assertSessionHasNoErrors();

    expect($cara->refresh()->manager_id)->toBe($eve->id);
});

it('rejects a Manager that is not an Employee before checking for loops', function (mixed $managerId, string $message) {
    $ben = Employee::factory()->create(['manager_id' => Employee::factory()->top()->create()->id]);

    actingAs(employeeWhoManagesEmployees())
        ->patch(route('employees.update', $ben), managerChange($ben, ['manager_id' => $managerId]))
        ->assertSessionHasErrors(['manager_id' => $message]);
})->with([
    'not a number' => ['ben', 'The Manager field must be an integer.'],
    'unknown' => [999999, 'The selected Manager is invalid.'],
]);
