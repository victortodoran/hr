<?php

namespace App\Concerns;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate Employee profiles.
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $employeeId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($employeeId),
        ];
    }

    /**
     * Get the validation rules used to validate Employee names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate Employee emails.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $employeeId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $employeeId === null
                ? Rule::unique(Employee::class)
                : Rule::unique(Employee::class)->ignore($employeeId),
        ];
    }
}
