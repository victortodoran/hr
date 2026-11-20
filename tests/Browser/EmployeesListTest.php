<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Models\Department;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class);

it('fits the Employees list on a phone without scrolling the page sideways, and opens an Employee from their row', function () {
    $department = Department::factory()->create(['name' => 'Research and Development']);
    $dana = Employee::factory()->create(['name' => 'Dana Alexandrescu-Whitfield', 'department_id' => $department->id]);
    Employee::factory()->count(3)->reportingTo($dana)->create(['department_id' => $department->id]);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]));

    visit(route('employees.index'))
        ->resize(390, 844)
        ->assertSee('Dana Alexandrescu-Whitfield')
        ->assertSee('Research and Development')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=link[name="Dana Alexandrescu-Whitfield"s]')
        ->assertPathIs("/employees/{$dana->id}")
        ->assertNoJavaScriptErrors();
});

it('filters the Employees by name, status chip, Department and Manager from the filter bar', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);
    $dana = Employee::factory()->create(['name' => 'Dana Top', 'department_id' => $finance->id]);
    Employee::factory()->reportingTo($dana)->create(['name' => 'Cara Lindqvist', 'department_id' => $finance->id]);
    Employee::factory()->future()->reportingTo($dana)->create(['name' => 'Cara Future', 'department_id' => $finance->id]);
    Employee::factory()->create(['name' => 'Cara Elsewhere']);
    Employee::factory()->create(['name' => 'Ben Smith']);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]));

    visit(route('employees.index'))
        ->fill('[aria-label="Search by name"]', 'cara')
        ->assertQueryStringHas('search', 'cara')
        ->assertDontSee('Ben Smith')
        ->assertSee('Current 2')
        ->click('[aria-label="Department"]')
        ->click('internal:role=option[name="Finance"s]')
        ->assertQueryStringHas('department', (string) $finance->id)
        ->assertDontSee('Cara Elsewhere')
        ->click('[aria-label="Manager"]')
        ->click('internal:role=option[name="Dana Top"s]')
        ->assertQueryStringHas('manager', (string) $dana->id)
        ->click('internal:role=button[name="Current 1"s]')
        ->assertQueryStringHas('status', 'current')
        ->assertSee('Cara Lindqvist')
        ->assertDontSee('Cara Future')
        ->fill('[aria-label="Search by name"]', 'nobody')
        ->assertSee('No Employees match these filters.')
        ->click('internal:role=button[name="Clear filters"s]')
        ->assertSee('Ben Smith')
        ->assertNoJavaScriptErrors();
});
