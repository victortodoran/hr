<?php

namespace App\Http\Requests\TimeOff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Declining Pending Time Off, which always needs a reason the Employee will
 * see.
 */
class TimeOffDeclineRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decline_reason' => ['required', 'string'],
        ];
    }

    /**
     * The reason given for declining.
     */
    public function reason(): string
    {
        return $this->string('decline_reason')->value();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decline_reason.required' => __('Give a reason for declining.'),
        ];
    }
}
