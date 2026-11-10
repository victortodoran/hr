<?php

use App\Http\Controllers\EmployeeController;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class, Employee::class);

it('lists who still reports to a Former Employee, so HR can pick them a new Manager', function () {
    $fay = Employee::factory()->former()->create();
    $cara = Employee::factory()->create(['name' => 'Cara Lane', 'manager_id' => $fay->id]);
    $dev = Employee::factory()->future()->create(['name' => 'Dev Patel', 'manager_id' => $fay->id]);
    Employee::factory()->former()->create(['manager_id' => $fay->id]);
    Employee::factory()->create(['manager_id' => $cara->id]);

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $fay))
        ->assertInertia(fn (Assert $page) => $page->where('reportsNeedingAManager', [
            ['id' => $cara->id, 'name' => 'Cara Lane'],
            ['id' => $dev->id, 'name' => 'Dev Patel'],
        ]));
});

it('lists nobody for a Manager who has not left', function () {
    $ben = Employee::factory()->create();
    Employee::factory()->create(['manager_id' => $ben->id]);

    actingAs(employeeWhoManagesEmployees())->get(route('employees.edit', $ben))
        ->assertInertia(fn (Assert $page) => $page->where('reportsNeedingAManager', []));
});
