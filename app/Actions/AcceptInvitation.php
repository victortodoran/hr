<?php

namespace App\Actions;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Lets an invited Employee set their password from the invitation email,
 * which also verifies their email.
 */
class AcceptInvitation
{
    /**
     * Set the invited Employee's password and verify their email, refusing
     * an invitation link that is invalid or has expired, and an Employee
     * whose Leaving Date has passed, who can't sign in anyway.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     *
     * @throws ValidationException
     */
    public function accept(array $credentials): void
    {
        $status = Password::broker('invites')->reset(
            $credentials,
            function (Employee $employee, string $password): void {
                if ($employee->employmentStatus() === EmploymentStatus::Former) {
                    throw ValidationException::withMessages(['email' => $employee->signInRefusal()]);
                }

                $employee->forceFill([
                    'password' => $password,
                    'email_verified_at' => now(),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('This invitation link is invalid or has expired. Ask HR to send a new one.'),
            ]);
        }
    }
}
