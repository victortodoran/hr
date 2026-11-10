<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetEmployeePassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the Employee's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(Employee $employee, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $employee->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
