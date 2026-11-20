<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\Company\DepartmentController;
use App\Http\Requests\Company\DepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(DepartmentController::class, DepartmentRequest::class, Department::class, Employee::class);

/**
 * An Employee who manages Departments, in a Department of their own.
 */
function admin(?Department $department = null): Employee
{
    $department ??= Department::factory()->create(['name' => 'Operations']);

    return employeeWith([Permission::ManageDepartments->value => null], ['department_id' => $department->id]);
}

it('lists the Departments by name for an Employee who manages them', function () {
    $admin = admin();
    $finance = Department::factory()->create(['name' => 'Finance']);

    actingAs($admin)->get(route('departments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('departments/Index')
            ->where('departments.0.id', $finance->id)
            ->where('departments.0.name', 'Finance')
            ->where('departments.1.name', 'Operations')
            ->etc(),
        );
});

it('counts each Department\'s Employees whose record the viewer may see, Former ones included, as the filtered Employees list shows them', function (Scope $scope, int $financeCount, int $salesCount) {
    $finance = Department::factory()->create(['name' => 'Finance']);
    $sales = Department::factory()->create(['name' => 'Sales']);
    $ana = employeeWith([
        Permission::ManageDepartments->value => null,
        Permission::ViewEmployeeRecords->value => $scope,
    ], ['department_id' => $sales->id]);
    Employee::factory()->reportingTo($ana)->create(['department_id' => $finance->id]);
    Employee::factory()->former()->reportingTo($ana)->create(['department_id' => $finance->id]);
    Employee::factory()->create(['department_id' => $finance->id]);

    actingAs($ana)->get(route('departments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('showsEmployeeCounts', true)
            ->where('departments.0.name', 'Finance')
            ->where('departments.0.employee_count', $financeCount)
            ->where('departments.1.name', 'Sales')
            ->where('departments.1.employee_count', $salesCount)
            ->etc(),
        );
})->with([
    'everyone' => [Scope::Everyone, 3, 1],
    'reports only' => [Scope::Reports, 2, 0],
]);

it('leaves out the Employee counts for a viewer who may not list Employees', function () {
    Employee::factory()->create(['department_id' => Department::factory()->create(['name' => 'Finance'])->id]);

    actingAs(admin())->get(route('departments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('showsEmployeeCounts', false)
            ->missing('departments.0.employee_count')
            ->etc(),
        );
});

it('refuses the Departments page to an Employee who does not manage Departments', function () {
    actingAs(employeeWith([Permission::ViewDirectory->value => null]))
        ->get(route('departments.index'))
        ->assertForbidden();
});

it('lets an Admin create a Department', function () {
    actingAs(admin())
        ->post(route('departments.store'), ['name' => 'Finance'])
        ->assertRedirect(route('departments.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Department added.']);

    expect(Department::query()->where('name', 'Finance')->exists())->toBeTrue();
});

it('rejects a Department name that is missing, too long or already taken', function (mixed $name, string $message) {
    actingAs(admin())
        ->post(route('departments.store'), ['name' => $name])
        ->assertSessionHasErrors(['name' => $message]);
})->with([
    'missing' => ['', 'The name field is required.'],
    'not text' => [['Finance'], 'The name field must be a string.'],
    'too long' => [str_repeat('a', 256), 'The name field must not be greater than 255 characters.'],
    'taken' => ['Operations', 'The name has already been taken.'],
]);

it('lets an Admin rename a Department, keeping its Employees', function () {
    $operations = Department::factory()->create(['name' => 'Ops']);
    $ana = Employee::factory()->create(['department_id' => $operations->id]);

    actingAs(admin())
        ->patch(route('departments.update', $operations), ['name' => 'Platform'])
        ->assertRedirect(route('departments.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Department renamed.']);

    expect($operations->refresh()->name)->toBe('Platform')
        ->and($ana->refresh()->department_id)->toBe($operations->id);
});

it('lets an Admin save a Department under its current name', function () {
    $admin = admin();

    actingAs($admin)
        ->patch(route('departments.update', $admin->department_id), ['name' => 'Operations'])
        ->assertSessionHasNoErrors();
});

it('refuses to rename a Department to another Department\'s name', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);

    actingAs(admin())
        ->patch(route('departments.update', $finance), ['name' => 'Operations'])
        ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

    expect($finance->refresh()->name)->toBe('Finance');
});

it('lets an Admin delete a Department with no Employees', function () {
    $finance = Department::factory()->create(['name' => 'Finance']);

    actingAs(admin())
        ->delete(route('departments.destroy', $finance))
        ->assertRedirect(route('departments.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Department deleted.']);

    expect(Department::query()->whereKey($finance->id)->exists())->toBeFalse();
});

it('refuses to delete a Department that still has Employees, and says why', function () {
    $admin = admin();

    actingAs($admin)
        ->delete(route('departments.destroy', $admin->department_id))
        ->assertSessionHasErrors(['department' => 'Operations still has Employees. Move them to another Department before deleting it.']);

    expect(Department::query()->whereKey($admin->department_id)->exists())->toBeTrue();
});

it('refuses every Department action to an Employee who does not manage Departments', function (string $method, Closure $url) {
    $finance = Department::factory()->create(['name' => 'Finance']);

    actingAs(employeeWith([Permission::ViewDirectory->value => null]))
        ->{$method}($url($finance), ['name' => 'Accounts'])
        ->assertForbidden();

    expect(Department::query()->where('name', 'Accounts')->exists())->toBeFalse()
        ->and($finance->fresh()?->name)->toBe('Finance');
})->with([
    'create' => ['post', fn (Department $department): string => route('departments.store')],
    'rename' => ['patch', fn (Department $department): string => route('departments.update', $department)],
    'delete' => ['delete', fn (Department $department): string => route('departments.destroy', $department)],
]);

it('tells every page which company-wide Permissions the viewer holds, so the menu only shows what they can use', function () {
    actingAs(admin())->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can', [Permission::ManageDepartments->value]));

    actingAs(employeeWith([Permission::ViewDirectory->value => null, Permission::ViewPay->value => Scope::Own]))->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can', [Permission::ViewDirectory->value]));
});
