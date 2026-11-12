<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(HandleInertiaRequests::class);

it('has no Dashboard', function () {
    actingAs(Employee::factory()->create())->get('/dashboard')->assertNotFound();
});

it('gives pages the signed-in Employee', function () {
    actingAs(Employee::factory()->create(['name' => 'Cara Lopez']))->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.employee.name', 'Cara Lopez')
            ->missing('auth.user'),
        );
});

it('tells the pages whether the signed-in Employee may open their own Employee page', function (array $grants, bool $expected) {
    actingAs(employeeWith($grants))->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canSeeOwnPage', $expected));
})->with([
    'own record' => [[Permission::ViewEmployeeRecords->value => Scope::Own], true],
    'own assignments' => [[Permission::ViewAssignments->value => Scope::Own], true],
    'reports only' => [[Permission::ViewEmployeeRecords->value => Scope::Reports], false],
    'neither' => [[Permission::ViewDirectory->value => null], false],
]);

it('tells the pages whether to offer the Employees list, which only helps when the viewer may see more than their own record', function (?Scope $scope, bool $expected) {
    actingAs(employeeWith($scope === null ? [Permission::ViewDirectory->value => null] : [Permission::ViewEmployeeRecords->value => $scope]))->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canListEmployees', $expected));
})->with([
    'own' => [Scope::Own, false],
    'reports' => [Scope::Reports, true],
    'everyone' => [Scope::Everyone, true],
    'none' => [null, false],
]);
