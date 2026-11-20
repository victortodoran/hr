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
        ->assertSee('Edit Eve Stan')
        ->assertSee('Former Employee')
        ->assertSee('Eve Stan has left, but these Employees still report to them')
        ->assertSeeLink('Lia Rusu')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});

it('saves a changed Leaving Date, and asks before leaving with unsaved changes', function () {
    $hr = employeeWith([Permission::ManageEmployees->value => Scope::Everyone, Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $cara = Employee::factory()->startedLongAgo()->create(['name' => 'Cara Lane']);

    actingAs($hr);

    $page = visit(route('employees.edit', $cara))
        ->assertSee('Edit Cara Lane');

    pickDate($page, '#leaving_date', '2099-06-30')
        ->click('internal:role=link[name="Cancel"s]')
        ->assertSee('Discard your changes?')
        ->click('internal:role=button[name="Keep editing"s]')
        ->assertPathIs("/employees/{$cara->id}/edit")
        ->click('internal:role=button[name="Save"s]')
        ->assertSee('Employee updated.')
        ->click('internal:role=link[name="Cancel"s]')
        ->assertPathIs("/employees/{$cara->id}")
        ->assertNoJavaScriptErrors();

    expect($cara->refresh()->leaving_date?->toDateString())->toBe('2099-06-30');
});
