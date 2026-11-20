<?php

use App\Enums\Permission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CompanySettings;

use function Pest\Laravel\actingAs;

covers(HandleInertiaRequests::class);

it('opens Company settings from one sidebar entry and moves through its sub-nav on a phone', function () {
    CompanySettings::factory()->create();
    actingAs(employeeWith([
        Permission::ManageCompany->value => null,
        Permission::ManageDepartments->value => null,
        Permission::ManageRoles->value => null,
    ]));

    visit(route('home'))
        ->resize(390, 844)
        ->click('[data-sidebar="trigger"]')
        ->click('internal:role=link[name="Company settings"s]')
        ->assertPathIs(route('company.edit', absolute: false))
        ->click('internal:role=link[name="Departments"s]')
        ->assertPathIs(route('departments.index', absolute: false))
        ->assertSeeIn('[data-test="top-bar-title"]', 'Departments')
        ->click('internal:role=link[name="Roles"s]')
        ->assertPathIs(route('roles.index', absolute: false))
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
