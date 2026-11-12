<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An Employee's Paid Days for one year: a whole number of days, 0 or more,
 * for the current year or a later one.
 */
class PaidDaysRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:'.today()->year, 'max:9999'],
            'days' => ['required', 'integer', 'min:0', 'max:366'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.min' => __('Paid Days can only be set from :year onward.', ['year' => today()->year]),
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
