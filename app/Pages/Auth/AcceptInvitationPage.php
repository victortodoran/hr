<?php

namespace App\Pages\Auth;

use App\Models\Employee;
use Illuminate\Validation\Rules\Password;

/**
 * The set-password page an invitation link opens, as the props it renders.
 */
final class AcceptInvitationPage
{
    /**
     * The invitation link's token and email, whether the link still works,
     * and the rules a new password must meet.
     *
     * @return array{token: string, email: string, isValid: bool, passwordRules: string}
     */
    public static function props(string $token, string $email): array
    {
        $invitee = Employee::query()->where('email', $email)->first();

        return [
            'token' => $token,
            'email' => $email,
            'isValid' => $invitee?->hasValidInvitation($token) ?? false,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];
    }
}
