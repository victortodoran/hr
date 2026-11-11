<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature test closures are bound to the application's TestCase, so they
| can call routes and use Laravel's testing helpers.
|
*/

pest()->extend(TestCase::class)->in('Feature');
pest()->use(RefreshDatabase::class)->in('Feature/Access', 'Feature/Departments', 'Feature/Employees', 'Feature/Install', 'Feature/Directory', 'Feature/Lifecycle', 'Feature/Projects', 'Feature/ReportingLine', 'Feature/Assignments', 'Feature/Pay', 'Feature/Company');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create an Employee holding one Role with the given Permissions. Map each
 * Permission value to its Scope, or to null for company-wide Permissions.
 *
 * @param  array<string, Scope|null>  $grants
 * @param  array<string, mixed>  $attributes
 */
function employeeWith(array $grants, array $attributes = []): Employee
{
    $employee = Employee::factory()->create($attributes);

    giveRole($employee, $grants);

    return $employee;
}

/**
 * An Employee who may add and edit any Employee, such as someone in HR.
 */
function employeeWhoManagesEmployees(): Employee
{
    return employeeWith([Permission::ManageEmployees->value => null]);
}

/**
 * An Employee who manages the company (including naming the top Employee)
 * as well as Employees, like the starter Admin.
 */
function employeeWhoManagesTheCompany(): Employee
{
    return employeeWith([Permission::ManageCompany->value => null, Permission::ManageEmployees->value => null]);
}

/**
 * Give an Employee a further Role with the given Permissions.
 *
 * @param  array<string, Scope|null>  $grants
 */
function giveRole(Employee $employee, array $grants): Role
{
    $role = Role::factory()->withPermissions($grants)->create();

    $employee->roles()->attach($role);

    return $role;
}

/**
 * An Employee's record as the edit form sends it, with some changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function employeeRecordChanging(Employee $employee, array $changes): array
{
    return [
        'name' => $employee->name,
        'email' => $employee->email,
        'department_id' => $employee->department_id,
        'manager_id' => $employee->manager_id,
        'start_date' => $employee->start_date->toDateString(),
        'leaving_date' => $employee->leaving_date?->toDateString(),
        ...$changes,
    ];
}
