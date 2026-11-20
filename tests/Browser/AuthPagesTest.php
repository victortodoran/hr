<?php

use App\Models\Employee;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\actingAs;

/**
 * Checks that a page fits a 390px phone screen without sideways scrolling.
 */
const AUTH_PAGE_FITS = 'document.documentElement.scrollWidth <= window.innerWidth';

it('asks for a reset link on a phone, then goes back to log in', function () {
    Notification::fake();
    Employee::factory()->startedLongAgo()->create(['email' => 'cara@acme.test']);

    visit(route('password.request'))
        ->resize(390, 844)
        ->assertPresent('[data-slot="auth-card"] [data-slot="app-mark"]')
        ->assertScript(AUTH_PAGE_FITS, true)
        ->fill('#email', 'cara@acme.test')
        ->click('internal:role=button[name="Email reset link"s]')
        ->assertSee('We have emailed your password reset link.')
        ->click('internal:role=link[name="Back to log in"s]')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

it('resets a password on a phone with the email shown read-only', function () {
    $cara = Employee::factory()->startedLongAgo()->create(['email' => 'cara@acme.test']);
    $token = Password::broker()->createToken($cara);

    visit(route('password.reset', ['token' => $token, 'email' => $cara->email]))
        ->resize(390, 844)
        ->assertValue('#email', 'cara@acme.test')
        ->assertAttribute('#email', 'readonly', '')
        ->assertScript(AUTH_PAGE_FITS, true)
        ->fill('#password', 'a-new-password')
        ->fill('#password_confirmation', 'a-new-password')
        ->click('internal:role=button[name="Reset password"s]')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

it('sets a password from an invitation on a phone with the email shown read-only', function () {
    $cara = Employee::factory()->invited()->create(['email' => 'cara@acme.test']);
    $token = Password::broker('invites')->createToken($cara);

    visit(route('invitation.show', ['token' => $token, 'email' => $cara->email]))
        ->resize(390, 844)
        ->assertAttribute('#email', 'readonly', '')
        ->assertScript(AUTH_PAGE_FITS, true)
        ->fill('#password', 'a-new-password')
        ->fill('#password_confirmation', 'a-new-password')
        ->click('internal:role=button[name="Set password"s]')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

it('confirms a password on a phone on the auth card', function () {
    actingAs(Employee::factory()->startedLongAgo()->create());

    visit(route('password.confirm'))
        ->resize(390, 844)
        ->assertPresent('[data-slot="auth-card"]')
        ->assertScript(AUTH_PAGE_FITS, true)
        ->fill('#password', 'password')
        ->click('internal:role=button[name="Confirm password"s]')
        ->assertPathIsNot('/user/confirm-password')
        ->assertNoJavaScriptErrors();
});

it('shows a missing page on a phone with Go to Home', function () {
    config(['app.debug' => false]);
    actingAs(Employee::factory()->startedLongAgo()->create());

    visit('/no-such-page')
        ->resize(390, 844)
        ->assertSee('Page not found')
        ->assertScript(AUTH_PAGE_FITS, true)
        ->click('internal:role=link[name="Go to Home"s]')
        ->assertPathIs('/home')
        ->assertNoJavaScriptErrors();
});
