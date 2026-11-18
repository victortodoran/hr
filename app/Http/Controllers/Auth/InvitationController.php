<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Pages\Auth\AcceptInvitationPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an invited Employee set their password from the invitation email.
 */
class InvitationController extends Controller
{
    /**
     * Show the set-password page for an invitation link, or say straight
     * away that the link no longer works.
     */
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/AcceptInvitation', AcceptInvitationPage::props($token, $request->string('email')->toString()));
    }

    /**
     * Set the invited Employee's password and verify their email.
     */
    public function store(AcceptInvitationRequest $request, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        /** @var array{email: string, password: string, password_confirmation: string, token: string} $credentials */
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $acceptInvitation->accept($credentials);

        return to_route('login')->with('status', __('Your password is set. You can now log in.'));
    }
}
