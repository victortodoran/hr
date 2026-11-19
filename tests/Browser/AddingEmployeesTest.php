<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Employees\EmployeeController;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

covers(EmployeeController::class);

it('adds an Employee with their Job Title and lands on their Employee page', function () {
    Notification::fake();
    Project::factory()->internal()->create();
    Department::factory()->create(['name' => 'Finance']);
    $hr = employeeWith([Permission::ManageEmployees->value => null, Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $hr->update(['name' => 'Ana Popescu']);

    actingAs($hr);

    visit(route('employees.create'))
        ->assertNoJavaScriptErrors()
        ->fill('#name', 'Cara Lane')
        ->fill('#email', 'cara@acme.test')
        ->click('#department_id')
        ->click('internal:role=option[name="Finance"s]')
        ->click('#manager_id')
        ->click('internal:role=option[name="Ana Popescu"s]')
        ->fill('#start_date', '2026-12-01')
        ->fill('#job_title', 'Payroll Specialist')
        ->click('internal:role=button[name="Add Employee"s]')
        ->assertSee('Employee added. An invitation has been sent to cara@acme.test.')
        ->assertPathIs(route('employees.show', Employee::query()->where('email', 'cara@acme.test')->sole(), absolute: false))
        ->assertNoJavaScriptErrors();
});
