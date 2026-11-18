<?php

namespace App\Pages\Settings;

use App\Models\Employee;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * The profile settings page as the props it renders.
 */
final class ProfilePage
{
    /**
     * Whether the Employee must verify their email, and the status left by
     * the last profile change, if any.
     *
     * @return array{mustVerifyEmail: bool, status: mixed}
     */
    public static function props(?Employee $employee, mixed $status): array
    {
        return [
            'mustVerifyEmail' => $employee instanceof MustVerifyEmail,
            'status' => $status,
        ];
    }
}
