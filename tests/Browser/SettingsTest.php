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
