<?php

namespace App\Http\Requests\TimeOff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An Employee's Paid Days: a whole number of days a year, 0 or more.
 */
class PaidDaysRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:0', 'max:366'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'days' => __('Paid Days'),
        ];
    }
}
