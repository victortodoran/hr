<?php

use App\Http\Controllers\Settings\SecurityController;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(SecurityController::class);

it('opens Settings on Security', function () {
    actingAs(Employee::factory()->create())->get('/settings')
        ->assertRedirect('/settings/security');
});

it('has no Profile settings page, so Employees change neither their name nor their email there', function (string $method) {
    $employee = Employee::factory()->create(['name' => 'Cara Lane', 'email' => 'cara@acme.test']);

    actingAs($employee)->call($method, '/settings/profile', ['name' => 'Someone Else', 'email' => 'someone@example.com', 'password' => 'password'])
        ->assertNotFound();

    $employee->refresh();

    expect($employee->name)->toBe('Cara Lane')
        ->and($employee->email)->toBe('cara@acme.test');
})->with(['GET', 'PATCH', 'DELETE']);
