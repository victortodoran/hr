<?php

use App\Models\Employee;

it('logs in on a phone, showing the app mark and fitting the screen', function () {
    Employee::factory()->startedLongAgo()->create(['email' => 'cara@acme.test']);

    visit(route('login'))
        ->resize(390, 844)
        ->assertPresent('[data-slot="app-mark"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->fill('#email', 'cara@acme.test')
        ->fill('#password', 'password')
        ->click('internal:role=button[name="Log in"s]')
        ->assertPathIs(route('landing', absolute: false))
        ->assertNoJavaScriptErrors();
});
