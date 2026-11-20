<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Company\DepartmentController;
use App\Models\Department;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(DepartmentController::class);

it('adds a Department in a dialog, links its Employee count to the Employees list, and explains why one in use can\'t be deleted', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);
    Employee::factory()->count(2)->create(['department_id' => $finance->id]);

    actingAs(employeeWith([
        Permission::ManageDepartments->value => null,
        Permission::ViewEmployeeRecords->value => Scope::Everyone,
    ], ['department_id' => $finance->id]));

    visit(route('departments.index'))
        ->click('internal:role=button[name="Add Department…"s]')
        ->fill('#new-department-name', 'Sales')
        ->click('internal:role=dialog >> internal:role=button[name="Add Department"s]')
        ->assertSee('Department added.')
        ->assertSee('Sales')
        ->hover('internal:role=link[name="3 Employees in Finance"s]')
        ->click('internal:role=button[name="More for Finance"s]')
        ->click('internal:role=menuitem[name="Delete…"s]')
        ->assertSee('Delete Finance')
        ->assertSee('3 Employees belong to Finance. Move them to another Department before deleting it.')
        ->click('internal:role=dialog >> internal:role=button[name="Delete Department"s]')
        ->assertSee('Finance still has Employees. Move them to another Department before deleting it.')
        ->click('internal:role=dialog >> internal:role=button[name="Cancel"s]')
        ->click('internal:role=link[name="3 Employees in Finance"s]')
        ->assertQueryStringHas('department', (string) $finance->id)
        ->assertNoJavaScriptErrors();
});

it('renames a Department from its ⋯ menu on a phone', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);

    actingAs(employeeWith([Permission::ManageDepartments->value => null], ['department_id' => $finance->id]));

    visit(route('departments.index'))
        ->resize(390, 844)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('internal:role=button[name="More for Finance"s]')
        ->click('internal:role=menuitem[name="Rename…"s]')
        ->assertValue('#department-name', 'Finance')
        ->fill('#department-name', 'Finance & Legal')
        ->click('internal:role=dialog >> internal:role=button[name="Save"s]')
        ->assertSee('Department renamed.')
        ->assertSee('Finance & Legal')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect($finance->refresh()->name)->toBe('Finance & Legal');
});
