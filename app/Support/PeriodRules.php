<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * How a Period in a URL's query is validated, wherever a page takes one:
 * a week, a month, or a custom period from one date to another of at most
 * Period::LONGEST_CUSTOM_MONTHS.
 */
final class PeriodRules
{
    /**
     * The rules for a Period in a URL's query: a week, a month, or a custom
     * period from one date to another.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'week' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Once From and To are valid on their own, refuse a custom period longer
     * than Period::LONGEST_CUSTOM_MONTHS.
     */
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
