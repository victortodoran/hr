<?php

use App\Http\Middleware\EnsureEmployeeCanSignIn;
use App\Listeners\RefuseSignInOutsideEmployment;
use App\Models\Employee;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

covers(RefuseSignInOutsideEmployment::class, EnsureEmployeeCanSignIn::class, Employee::class);

beforeEach(fn () => travelTo('2026-03-01 09:00'));

/**
 * @param  array<string, mixed>  $dates
 */
function employeeWithDates(array $dates): Employee
{
    return Employee::factory()->create(['email' => 'cara@acme.test', ...$dates]);
}

function signIn(): void
{
    post(route('login.store'), ['email' => 'cara@acme.test', 'password' => 'password']);
}

it('refuses sign-in before the Start Date, and says when it opens', function () {
    employeeWithDates(['start_date' => '2026-03-02']);

    post(route('login.store'), ['email' => 'cara@acme.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'You can sign in from your Start Date, 2 March 2026.']);

    assertGuest();
});

it('allows sign-in from the Start Date up to and including the Leaving Date', function (string $today) {
    $cara = employeeWithDates(['start_date' => '2026-03-02', 'leaving_date' => '2026-06-30']);
    travelTo($today);

    signIn();

    assertAuthenticatedAs($cara);
})->with(['on the Start Date' => '2026-03-02 08:00', 'on the Leaving Date' => '2026-06-30 23:00']);

it('refuses sign-in from the day after the Leaving Date', function () {
    employeeWithDates(['start_date' => '2025-01-01', 'leaving_date' => '2026-02-28']);

    post(route('login.store'), ['email' => 'cara@acme.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'You can no longer sign in because you have left the company.']);

    assertGuest();
});

it('ends an existing session on the first request after the Leaving Date', function () {
    $cara = employeeWithDates(['start_date' => '2025-01-01', 'leaving_date' => '2026-03-01']);

    actingAs($cara)->get(route('profile.edit'))->assertOk();
    session()->put('draft', 'unsaved notes');
    $token = session()->token();

    travelTo('2026-03-02 00:01');

    get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'You can no longer sign in because you have left the company.'])
        ->assertSessionMissing('draft');

    assertGuest();
    expect(session()->token())->not->toBe($token);
});

it('lets a Future Employee set their password before their Start Date', function () {
    $cara = Employee::factory()->invited()->future()->create();
    $token = Password::broker('invites')->createToken($cara);

    post(route('invitation.store'), [
        'token' => $token,
        'email' => $cara->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('login'));

    expect($cara->refresh()->hasPendingInvitation())->toBeFalse();
    assertGuest();
});

it('refuses sign-in through the two-factor challenge outside employment too', function () {
    $cara = employeeWithDates(['start_date' => '2026-03-02', 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);

    post(route('login.store'), ['email' => 'cara@acme.test', 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
        ->assertSessionHasErrors(['email' => 'You can sign in from your Start Date, 2 March 2026.']);

    assertGuest();
    expect($cara->refresh()->two_factor_confirmed_at)->not->toBeNull();
});
