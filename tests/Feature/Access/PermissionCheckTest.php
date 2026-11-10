<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\RolePermission;
use App\Providers\AccessServiceProvider;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

covers(AccessServiceProvider::class, Employee::class, Permission::class, Scope::class, RolePermission::class);

/*
 * No domain pages exist yet, so these routes stand in for any page guarded
 * by a Permission: one about a target Employee, one company-wide.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/_test/employees/{employee}', fn (Employee $employee) => 'ok')
            ->middleware('can:'.Permission::ViewEmployeeRecords->value.',employee');
        Route::get('/_test/departments', fn () => 'ok')
            ->middleware('can:'.Permission::ManageDepartments->value);
    });
});

it('lets an Employee with Scope everyone act on any Employee', function () {
    $hr = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $colleague = Employee::factory()->create();

    actingAs($hr)->get("/_test/employees/{$colleague->id}")->assertOk();
    actingAs($hr)->get("/_test/employees/{$hr->id}")->assertOk();
});

it('lets an Employee with Scope own act only on themselves', function () {
    $cara = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Own]);
    $colleague = Employee::factory()->create();

    actingAs($cara)->get("/_test/employees/{$cara->id}")->assertOk();
    actingAs($cara)->get("/_test/employees/{$colleague->id}")->assertForbidden();
});

it('refuses an Employee whose Roles lack the Permission', function () {
    $cara = employeeWith([Permission::ViewPay->value => Scope::Everyone]);

    actingAs($cara)->get("/_test/employees/{$cara->id}")->assertForbidden();
});

it('uses the widest Scope across all of an Employee\'s Roles', function () {
    $ana = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Own]);
    giveRole($ana, [Permission::ViewEmployeeRecords->value => Scope::Everyone]);
    $colleague = Employee::factory()->create();

    actingAs($ana)->get("/_test/employees/{$colleague->id}")->assertOk();
});

it('does not yet let Scope reports cover other Employees', function () {
    $ben = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Reports]);
    $colleague = Employee::factory()->create();

    actingAs($ben)->get("/_test/employees/{$colleague->id}")->assertForbidden();
});

it('allows a company-wide Permission only to Employees holding it', function () {
    $admin = employeeWith([Permission::ManageDepartments->value => null]);
    $cara = employeeWith([Permission::ViewDirectory->value => null]);

    actingAs($admin)->get('/_test/departments')->assertOk();
    actingAs($cara)->get('/_test/departments')->assertForbidden();
});
