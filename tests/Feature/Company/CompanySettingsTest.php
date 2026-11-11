<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Requests\CompanySettingsRequest;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\PayEntry;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

covers(CompanySettingsController::class, CompanySettingsRequest::class, CompanySettings::class);

beforeEach(fn () => CompanySettings::factory()->create(['name' => 'Acme', 'currency' => 'EUR']));

/**
 * An Employee who may change the company settings, like the starter Admin.
 */
function companyManager(): Employee
{
    return employeeWith([Permission::ManageCompany->value => null]);
}

it('shows the company settings, with the currency open to change while no Pay exists', function () {
    actingAs(companyManager())->get(route('company.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/Edit')
            ->where('company', ['name' => 'Acme', 'currency' => 'EUR'])
            ->where('isCurrencyLocked', false));
});

it('shows the currency as locked once any Pay exists', function () {
    PayEntry::factory()->create();

    actingAs(companyManager())->get(route('company.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('isCurrencyLocked', true));
});

it('lets someone who manages the company rename it and change the currency while no Pay exists', function () {
    actingAs(companyManager())
        ->patch(route('company.update'), ['name' => 'Acme Software', 'currency' => 'usd'])
        ->assertRedirect(route('company.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Company settings saved.']);

    expect(CompanySettings::query()->sole()->only('name', 'currency'))->toBe(['name' => 'Acme Software', 'currency' => 'USD']);
});

it('refuses to change the currency once any Pay exists, but still renames the company', function () {
    PayEntry::factory()->create();

    actingAs(companyManager())
        ->patch(route('company.update'), ['name' => 'Acme Software', 'currency' => 'USD'])
        ->assertSessionHasErrors(['currency' => 'The currency can\'t be changed once any Pay has been recorded.']);

    expect(CompanySettings::query()->sole()->only('name', 'currency'))->toBe(['name' => 'Acme', 'currency' => 'EUR']);

    actingAs(companyManager())
        ->patch(route('company.update'), ['name' => 'Acme Software', 'currency' => 'EUR'])
        ->assertSessionHasNoErrors();

    expect(CompanySettings::query()->sole()->name)->toBe('Acme Software');
});

it('rejects invalid company settings and explains why', function (array $changes, string $field, string $message) {
    actingAs(companyManager())
        ->patch(route('company.update'), ['name' => 'Acme', 'currency' => 'EUR', ...$changes])
        ->assertSessionHasErrors([$field => $message]);

    expect(CompanySettings::query()->sole()->only('name', 'currency'))->toBe(['name' => 'Acme', 'currency' => 'EUR']);
})->with([
    'name missing' => [['name' => ''], 'name', 'The name field is required.'],
    'name not text' => [['name' => ['Acme']], 'name', 'The name field must be a string.'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name', 'The name field must not be greater than 255 characters.'],
    'currency missing' => [['currency' => ''], 'currency', 'The currency field is required.'],
    'currency not a code' => [['currency' => 'EURO'], 'currency', 'The currency must be a three-letter code, such as EUR.'],
    'currency not text' => [['currency' => ['EUR']], 'currency', 'The currency must be a three-letter code, such as EUR.'],
]);

it('keeps the company settings to those who manage the company', function () {
    $employee = employeeWith([Permission::ManagePay->value => Scope::Everyone, Permission::ManageEmployees->value => null]);

    actingAs($employee)->get(route('company.edit'))->assertForbidden();
    actingAs($employee)->patch(route('company.update'), ['name' => 'Taken', 'currency' => 'USD'])->assertForbidden();

    expect(CompanySettings::query()->sole()->name)->toBe('Acme');
});
