<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an invited Employee set their password from the invitation email.
 */
class InvitationController extends Controller
{
    /**
     * Show the set-password page for an invitation link.
     */
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Set the invited Employee's password and verify their email.
     */
    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $status = PasswordBroker::broker('invites')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Employee $employee, string $password): void {
                $employee->forceFill([
                    'password' => $password,
                    'email_verified_at' => now(),
                ])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('This invitation link is invalid or has expired. Ask HR to send a new one.'),
            ]);
        }

        return to_route('login')->with('status', __('Your password is set. You can now log in.'));
    }
}
