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
    $hr = employeeWith([Permission::AddEmployees->value => null, Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $hr->update(['name' => 'Ana Popescu']);

    actingAs($hr);

    $page = visit(route('employees.create'))
        ->assertNoJavaScriptErrors()
        ->fill('#name', 'Cara Lane')
        ->fill('#email', 'cara@acme.test')
        ->click('#department_id')
        ->click('internal:role=option[name="Finance"s]')
        ->click('#manager_id')
        ->click('internal:role=option[name="No Manager"s]')
        ->assertSee('With no Manager, nobody decides for Cara Lane')
        ->click('#manager_id')
        ->click('internal:role=option[name="Ana Popescu"s]')
        ->assertDontSee('With no Manager');

    pickDate($page, '#start_date', '2026-12-01')
        ->fill('#job_title', 'Payroll Specialist')
        ->click('internal:role=button[name="Add Employee"s]')
        ->assertSee('Employee added. An invitation has been sent to cara@acme.test.')
        ->assertPathIs(route('employees.show', Employee::query()->where('email', 'cara@acme.test')->sole(), absolute: false))
        ->assertNoJavaScriptErrors();

    expect(Employee::query()->where('email', 'cara@acme.test')->sole()->start_date->toDateString())->toBe('2026-12-01');
});

it('fits the Add Employee form on a phone, its paired dates stacked, and cancels back to the Employees', function () {
    actingAs(employeeWith([Permission::AddEmployees->value => null, Permission::ViewEmployeeRecords->value => Scope::Everyone]));

    visit(route('employees.create'))
        ->resize(390, 844)
        ->assertSee('Employment')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript("document.querySelector('[role=group]:has(> #start_date)').getBoundingClientRect().bottom <= document.querySelector('[role=group]:has(> #leaving_date)').getBoundingClientRect().top", true)
        ->click('internal:role=link[name="Cancel"s]')
        ->assertPathIs('/employees')
        ->assertNoJavaScriptErrors();
});
