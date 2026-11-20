<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(HandleInertiaRequests::class, Employee::class);

it('tells every page how far beyond themselves the viewer sees each area, for the Scope switch', function () {
    $viewer = employeeWith([
        Permission::ViewWorkEntries->value => Scope::Own,
        Permission::ReopenWorkEntries->value => Scope::Everyone,
        Permission::ViewTimeOff->value => Scope::Reports,
        Permission::ManagePaidDays->value => Scope::Own,
        Permission::ViewTravel->value => Scope::Own,
    ]);

    actingAs($viewer)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.othersReach', [
            'work' => 'everyone',
            'timeOff' => 'reports',
            'travel' => null,
            'performance' => null,
        ]));
});

it('counts a Manager as seeing their Reports\' Performance Evaluations without a Permission for it', function () {
    $ben = managerBen();
    Employee::factory()->reportingTo($ben)->create();

    actingAs($ben)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.othersReach.performance', 'reports'));
});
