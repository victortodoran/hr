<?php

namespace App\Pages\Settings;

use Illuminate\Validation\Rules\Password;

/**
 * The security settings page as the props it renders.
 */
final class SecurityPage
{
    /**
     * The rules a new password must meet.
     *
     * @return array{passwordRules: string}
     */
    public static function props(): array
    {
        return [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];
    }
}
