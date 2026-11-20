<?php

namespace App\Http\Requests\Company;

use App\Models\CompanySettings;
use App\Rules\CurrencyInUse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompanySettingsRequest extends FormRequest
{
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
            'currency' => ['required', new CurrencyInUse],
        ];
    }

    /**
     * Every Pay and Travel Expense is recorded in the currency (ADR 0003), so it can't change once any exists.
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
