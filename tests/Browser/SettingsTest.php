<?php

use App\Http\Controllers\Settings\ProfileController;

use function Pest\Laravel\actingAs;

covers(ProfileController::class);

it('shows Settings without Appearance and stays light on a dark-mode computer', function () {
    actingAs(recorder());

    visit(route('profile.edit'))
        ->inDarkMode()
        ->assertSeeLink('Profile')
        ->assertSeeLink('Security')
        ->assertDontSeeLink('Appearance')
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertScript('getComputedStyle(document.body).backgroundColor', 'rgb(255, 255, 255)')
        ->assertNoJavaScriptErrors();
});

it('shows the Settings sub-nav above the form on a phone', function () {
    actingAs(recorder());

    visit(route('profile.edit'))
        ->resize(390, 844)
        ->assertSeeIn('[data-test="top-bar-title"]', 'Settings')
        ->assertAttribute('internal:role=link[name="Profile"s]', 'aria-current', 'page')
        ->assertSeeLink('Security')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
});
