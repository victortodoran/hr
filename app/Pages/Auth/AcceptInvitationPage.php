<?php

namespace App\Pages\Auth;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Validation\Rules\Password;

final class AcceptInvitationPage
{
    /** @return array{token: string, email: string, refusal: ?string, passwordRules: string} */
    public static function props(string $token, string $email): array
    {
        $invitee = Employee::query()->where('email', $email)->first();

        return [
            'token' => $token,
            'email' => $email,
            'refusal' => self::refusal($invitee, $token),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];
    }

    /** Once the Employee has left, the link is refused the way signing in is. */
    private static function refusal(?Employee $invitee, string $token): ?string
    {
        if ($invitee?->hasValidInvitation($token) !== true) {
            return __('It is invalid or has expired. Ask HR to send a new one.');
        }

        return $invitee->employmentStatus() === EmploymentStatus::Former ? $invitee->signInRefusal() : null;
    }
}
