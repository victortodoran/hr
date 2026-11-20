<?php

namespace App\Pages\Settings;

use Illuminate\Validation\Rules\Password;

final class SecurityPage
{
    /** @return array{passwordRules: string} */
    public static function props(): array
    {
        return [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];
    }
}
