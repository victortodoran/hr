<?php

namespace App\Http\Requests;

use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Work Entries week page's query: whose Work Entries, and the Period
 * shown, a week, a month or a custom period of at most three months.
 */
class WorkEntryPeriodRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee' => ['nullable', 'integer'],
            'week' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Once From and To are valid on their own, check the custom period is
     * short enough.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled(['from', 'to']) || $validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                if (! Period::fitsCustom(CarbonImmutable::parse($this->string('from')->value()), CarbonImmutable::parse($this->string('to')->value()))) {
                    $validator->errors()->add('to', __('A custom period can be at most :months months long.', ['months' => Period::LONGEST_CUSTOM_MONTHS]));
                }
            },
        ];
    }

    /**
     * The Period asked for, the current week when none is.
     */
    public function period(): Period
    {
        return Period::fromQuery($this->query()) ?? Period::week(today());
    }
}
