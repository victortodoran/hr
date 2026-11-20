<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class);

it('asks for a new Manager for the reports of an Employee who has left, fitting a phone', function () {
    $hr = employeeWith([Permission::ManageEmployees->value => Scope::Everyone, Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $eve = Employee::factory()->former()->create(['name' => 'Eve Stan']);
    Employee::factory()->startedLongAgo()->reportingTo($eve)->create(['name' => 'Lia Rusu']);

    actingAs($hr);

    visit(route('employees.edit', $eve))
        ->resize(390, 844)
        ->assertSee('Eve Stan has left, but these Employees still report to them')
        ->assertSeeLink('Lia Rusu')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
