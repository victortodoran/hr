<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\WorkEntriesTab;
use App\Models\Employee;
use App\Support\Period;
use App\Support\PeriodRules;
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
            ...PeriodRules::rules(),
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
        return [fn (Validator $validator) => PeriodRules::checkCustomLength($this, $validator)];
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

    /**
     * The Employee whose Work Entries are asked for, the viewer when none
     * is, or null when the one asked for doesn't exist.
     */
    public function employee(Employee $viewer): ?Employee
    {
        return $this->filled('employee') ? Employee::query()->find($this->integer('employee')) : $viewer;
    }
}
