<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Models\Department;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class);

it('fits the Employees list on a phone without scrolling the page sideways', function () {
    $department = Department::factory()->create(['name' => 'Research and Development']);
    $dana = Employee::factory()->create(['name' => 'Dana Alexandrescu-Whitfield', 'department_id' => $department->id]);
    Employee::factory()->count(3)->reportingTo($dana)->create(['department_id' => $department->id]);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]));

    visit(route('employees.index'))
        ->on()->mobile()
        ->assertSee('Dana Alexandrescu-Whitfield')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->assertNoJavaScriptErrors();
});

it('filters the Employees by name and status from the filter bar', function () {
    Employee::factory()->create(['name' => 'Cara Lindqvist']);
    Employee::factory()->future()->create(['name' => 'Cara Future']);
    Employee::factory()->create(['name' => 'Ben Smith']);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]));

    visit(route('employees.index'))
        ->fill('[aria-label="Search by name"]', 'cara')
        ->assertQueryStringHas('search', 'cara')
        ->assertDontSee('Ben Smith')
        ->assertSee('Cara Future')
        ->click('[aria-label="Status"]')
        ->click('internal:role=option[name="Current"s]')
        ->assertQueryStringHas('status', 'current')
        ->assertQueryStringHas('search', 'cara')
        ->assertSee('Cara Lindqvist')
        ->assertDontSee('Cara Future')
        ->assertNoJavaScriptErrors();
});
