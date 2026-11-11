<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\LandingController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

covers(LandingController::class, HandleInertiaRequests::class);

it('sends a guest to sign in instead of landing', function () {
    get(route('landing'))->assertRedirect(route('login'));
});

it('lands an Employee on their own page after signing in', function () {
    $ana = employeeWith([Permission::ViewEmployeeRecords->value => Scope::Own]);

    post(route('login.store'), ['email' => $ana->email, 'password' => 'password'])
        ->assertRedirect(route('landing', absolute: false));

    actingAs($ana)->get(route('landing'))->assertRedirect(route('employees.show', $ana));
});

it('lands an Employee who can\'t see their own page on the directory, or else their profile settings', function (array $grants, string $expected) {
    actingAs(employeeWith($grants))->get(route('landing'))->assertRedirect(route($expected));
})->with([
    'directory' => [[Permission::ViewDirectory->value => null], 'directory.index'],
    'neither' => [[Permission::ViewEmployeeRecords->value => Scope::Reports], 'profile.edit'],
]);

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
