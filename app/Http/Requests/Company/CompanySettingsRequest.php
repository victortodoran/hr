<?php

namespace App\Http\Requests\Company;

use App\Models\CompanySettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The company's name and currency. Every Pay and Travel Expense is
 * recorded in the currency (ADR 0003), so it can't change once any exists.
 */
class CompanySettingsRequest extends FormRequest
{
    /**
     * Take the currency code in capitals, however it was typed.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.regex' => __('The currency must be a three-letter code, such as EUR.'),
        ];
    }

    /**
     * Once the currency is valid on its own, refuse to change it if any Pay
     * has been recorded in the current one.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('currency')) {
                    return;
                }

                if ($this->input('currency') !== CompanySettings::query()->sole()->currency && CompanySettings::isCurrencyLocked()) {
                    $validator->errors()->add('currency', __('The currency can\'t be changed once any Pay or Travel Expense has been recorded.'));
                }
            },
        ];
    }
}
