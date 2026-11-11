<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('sends guests from the home page straight to sign in', function () {
    get('/')->assertRedirect(route('login'));
});

it('sends a signed-in Employee from the home page to where they land', function () {
    actingAs(Employee::factory()->create())
        ->get('/')
        ->assertRedirect(route('landing'));
});
