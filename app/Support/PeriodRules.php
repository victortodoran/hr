<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PeriodRules
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'week' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public static function checkCustomLength(FormRequest $request, Validator $validator): void
    {
        if (! $request->filled(['from', 'to']) || $validator->errors()->hasAny(['from', 'to'])) {
            return;
        }

        if (! Period::fitsCustom(CarbonImmutable::parse($request->string('from')->value()), CarbonImmutable::parse($request->string('to')->value()))) {
            $validator->errors()->add('to', __('A custom period can be at most :months months long.', ['months' => Period::LONGEST_CUSTOM_MONTHS]));
        }
    }
}
