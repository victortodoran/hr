<?php

use App\Actions\Auth\AcceptInvitation;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\Employee;
use App\Notifications\Invitation;
use App\Pages\Auth\AcceptInvitationPage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

covers(InvitationController::class, AcceptInvitation::class, AcceptInvitationPage::class, AcceptInvitationRequest::class, Invitation::class, Employee::class);

function inviteAndCaptureToken(Employee $employee): string
{
    Notification::fake();

    $employee->sendInvitation();

    $token = null;
    Notification::assertSentTo($employee, Invitation::class, function (Invitation $invitation) use (&$token) {
        $token = $invitation->token;

        return true;
    });

    return (string) $token;
}

it('shows the set-password page from the invitation link', function () {
    $employee = Employee::factory()->invited()->create();
    $token = inviteAndCaptureToken($employee);

    get(route('invitation.show', ['token' => $token, 'email' => $employee->email]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AcceptInvitation')
            ->where('email', $employee->email)
            ->where('token', $token)
            ->where('refusal', null)
            ->has('passwordRules'),
        );
});

it('tells the person straight away when the invitation link no longer works', function (Closure $linkFor) {
    $employee = Employee::factory()->invited()->create();

    get($linkFor($employee))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AcceptInvitation')
            ->where('refusal', 'It is invalid or has expired. Ask HR to send a new one.'),
        );
})->with([
    'expired' => [function (Employee $employee): string {
        $token = inviteAndCaptureToken($employee);
        travel(8)->days();

        return route('invitation.show', ['token' => $token, 'email' => $employee->email]);
    }],
    'made-up token' => [fn (Employee $employee): string => route('invitation.show', ['token' => 'not-a-real-token', 'email' => $employee->email])],
    'someone else\'s email' => [fn (Employee $employee): string => route('invitation.show', ['token' => inviteAndCaptureToken($employee), 'email' => 'nobody@acme.test'])],
]);

it('lets the invited Employee set a password, verifying their email, then sign in', function () {
    $employee = Employee::factory()->invited()->create();
    $token = inviteAndCaptureToken($employee);

    post(route('invitation.store'), [
        'token' => $token,
        'email' => $employee->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('login'));

    expect($employee->refresh()->hasVerifiedEmail())->toBeTrue();

    post(route('login.store'), ['email' => $employee->email, 'password' => 'a-new-password'])
        ->assertRedirect(route('landing', absolute: false));
});

it('refuses an invitation once the Employee has left, as signing in is refused', function () {
    $employee = Employee::factory()->invited()->create();
    $token = inviteAndCaptureToken($employee);
    $employee->update(['start_date' => today()->subYear(), 'leaving_date' => today()->subDay()]);

    get(route('invitation.show', ['token' => $token, 'email' => $employee->email]))
        ->assertInertia(fn (Assert $page) => $page->where('refusal', 'You can no longer sign in because you have left the company.'));

    post(route('invitation.store'), [
        'token' => $token,
        'email' => $employee->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasErrors(['email' => 'You can no longer sign in because you have left the company.']);

    expect($employee->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('keeps the invitation valid for a week', function () {
    $employee = Employee::factory()->invited()->create();
    $token = inviteAndCaptureToken($employee);

    travel(6)->days();

    post(route('invitation.store'), [
        'token' => $token,
        'email' => $employee->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('login'));
});

it('refuses an expired invitation', function () {
    $employee = Employee::factory()->invited()->create();
    $token = inviteAndCaptureToken($employee);

    travel(8)->days();

    post(route('invitation.store'), [
        'token' => $token,
        'email' => $employee->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasErrors('email');

    expect($employee->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('requires the password to be confirmed', function () {
    $employee = Employee::factory()->invited()->create();
    $token = inviteAndCaptureToken($employee);

    post(route('invitation.store'), [
        'token' => $token,
        'email' => $employee->email,
        'password' => 'a-new-password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');
});

it('sends an email with a link to set a password that expires in a week', function () {
    $employee = Employee::factory()->invited()->create(['email' => 'cara@acme.test']);
    $token = inviteAndCaptureToken($employee);

    $mail = (new Invitation($token))->toMail($employee);

    expect($mail->subject)->toBe('You have been invited to '.config('app.name'))
        ->and($mail->actionText)->toBe('Set password')
        ->and($mail->actionUrl)->toBe(route('invitation.show', ['token' => $token, 'email' => 'cara@acme.test']))
        ->and(Str::of(implode(' ', $mail->outroLines))->contains('7 days'))->toBeTrue();
});
