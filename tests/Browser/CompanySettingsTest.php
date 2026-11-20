<?php

use App\Enums\Permission;
use App\Http\Controllers\Company\CompanySettingsController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CompanySettings;
use App\Models\PayEntry;

use function Pest\Laravel\actingAs;

covers(HandleInertiaRequests::class, CompanySettingsController::class);

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

it('saves the company name on a phone, ticking Saved, with the locked currency shown read-only', function () {
    CompanySettings::factory()->create(['name' => 'Acme', 'currency' => 'EUR']);
    PayEntry::factory()->create();
    actingAs(employeeWith([Permission::ManageCompany->value => null]));

    visit(route('company.edit'))
        ->resize(390, 844)
        ->assertSee('Locked, as Pay or Travel Expenses are recorded in it.')
        ->assertButtonDisabled('Cancel')
        ->fill('#company-name', 'Acme Group')
        ->click('Save')
        ->assertSee('Saved')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();

    expect(CompanySettings::query()->sole()->only('name', 'currency'))->toBe(['name' => 'Acme Group', 'currency' => 'EUR']);
});
