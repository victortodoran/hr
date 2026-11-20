<?php

namespace App\Http\Requests\Travel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TravelRequestDeclineRequest extends FormRequest
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
