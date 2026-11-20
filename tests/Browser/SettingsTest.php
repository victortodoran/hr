<?php

use App\Http\Controllers\Settings\SecurityController;
use App\Models\Employee;

use function Pest\Laravel\actingAs;

covers(SecurityController::class);

it('opens Your profile from the user menu on the Employee\'s own page', function () {
    $cara = recorder();

    actingAs($cara);

    visit(route('landing'))
        ->click('[data-test="sidebar-menu-button"]')
        ->assertSee('Log out')
        ->click('internal:role=menuitem[name="Your profile"s]')
        ->assertPathIs(route('employees.show', $cara, false))
        ->assertNoJavaScriptErrors();
});

it('opens Settings from the user menu on Security, its only section, and stays light on a dark-mode computer', function () {
    actingAs(recorder());

    visit(route('landing'))
        ->inDarkMode()
        ->click('[data-test="sidebar-menu-button"]')
        ->click('internal:role=menuitem[name="Settings"s]')
        ->fill('#password', 'password')
        ->click('internal:role=button[name="Confirm password"s]')
        ->assertPathIs(route('security.edit', absolute: false))
        ->assertSeeLink('Security')
        ->assertDontSeeLink('Profile')
        ->assertDontSeeLink('Appearance')
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertScript('getComputedStyle(document.body).backgroundColor', 'rgb(255, 255, 255)')
        ->assertNoJavaScriptErrors();
});

it('opens the user menu and Settings on a phone', function () {
    actingAs(Employee::factory()->startedLongAgo()->create());

    visit(route('landing'))
        ->resize(390, 844)
        ->click('[data-sidebar="trigger"]')
        ->click('[data-test="sidebar-menu-button"]')
        ->assertSee('Your profile')
        ->assertScript('[...document.querySelectorAll(\'[role="menuitem"]\')].every((item) => item.getBoundingClientRect().height >= 44)', true)
        ->click('internal:role=menuitem[name="Settings"s]')
        ->fill('#password', 'password')
        ->click('internal:role=button[name="Confirm password"s]')
        ->assertPathIs(route('security.edit', absolute: false))
        ->assertSeeIn('[data-test="top-bar-title"]', 'Settings')
        ->assertAttribute('internal:role=link[name="Security"s]', 'aria-current', 'page')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
