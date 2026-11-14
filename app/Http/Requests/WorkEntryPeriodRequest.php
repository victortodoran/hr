<?php

namespace App\Http\Requests;

use App\Enums\WorkEntriesTab;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Work Entries week page's query: whose Work Entries, the tab, and the
 * Period shown, a week, a month or a custom period of at most three
 * months. The Calendar always shows a month.
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
            'tab' => ['nullable', 'string'],
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
     * The tab asked for: the Calendar when asked for, the List for anything
     * else or no tab.
     */
    public function tab(): WorkEntriesTab
    {
        return WorkEntriesTab::tryFrom($this->string('tab')->value()) ?? WorkEntriesTab::List;
    }

    /**
     * The Period asked for, the current week when none is. On the Calendar
     * it is the month holding the first day of the Period asked for, the
     * current month when none is.
     */
    public function period(): Period
    {
        $period = Period::fromQuery($this->query());

        if ($this->tab() === WorkEntriesTab::Calendar) {
            return Period::month(($period === null ? today() : $period->from)->format('Y-m'));
        }

        return $period ?? Period::week(today());
    }
}
