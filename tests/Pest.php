<?php

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
pest()->use(RefreshDatabase::class)->in('Feature/Access', 'Feature/Departments', 'Feature/Install');

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
