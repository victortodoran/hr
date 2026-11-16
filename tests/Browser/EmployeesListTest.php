<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\EmployeeController;
use App\Models\Department;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class);

it('fits the Employees list on a phone without scrolling the page sideways', function () {
    $department = Department::factory()->create(['name' => 'Research and Development']);
    $dana = Employee::factory()->top()->create(['name' => 'Dana Alexandrescu-Whitfield', 'department_id' => $department->id]);
    Employee::factory()->count(3)->reportingTo($dana)->create(['department_id' => $department->id]);

    actingAs(employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]));

    visit(route('employees.index'))
        ->on()->mobile()
        ->assertSee('Dana Alexandrescu-Whitfield')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth')
        ->assertNoJavaScriptErrors();
});
