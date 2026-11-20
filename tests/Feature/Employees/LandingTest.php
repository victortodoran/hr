<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(HandleInertiaRequests::class);

it('gives pages the signed-in Employee', function () {
    actingAs(Employee::factory()->create(['name' => 'Cara Lopez']))->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.employee.name', 'Cara Lopez')
            ->missing('auth.user'),
        );
});

it('tells the pages whether to offer the Employees list, which only helps when the viewer may see more than their own record', function (?Scope $scope, bool $expected) {
    actingAs(employeeWith($scope === null ? [Permission::ViewDirectory->value => null] : [Permission::ViewEmployeeRecords->value => $scope]))->get(route('landing'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canListEmployees', $expected));
})->with([
    'own' => [Scope::Own, false],
    'reports' => [Scope::Reports, true],
    'everyone' => [Scope::Everyone, true],
    'none' => [null, false],
]);
