<?php

namespace App\Http\Requests\WorkEntries;

use App\Support\Period;
use App\Support\PeriodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Filters for a Period of Work Entries: the Period, a week, a month or a
 * custom period of at most three months, and an Employee and a Project,
 * each optional.
 */
class WorkEntryFiltersRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...PeriodRules::rules(),
            'employee' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => PeriodRules::checkCustomLength($this, $validator)];
    }

    /**
     * The filters, null where none is set. The Period is the current month
     * when none is asked for. As Work Entries are never in the future, one
     * starting after today gives way to the current week, for a week, or
     * else the current month, and a custom one ends today at the latest.
     *
     * @return array{period: Period, employee: int|null, project: int|null}
     */
    public function filters(): array
    {
        return [
            'period' => (Period::fromQuery($this->query()) ?? Period::month(today()->format('Y-m')))->notAfterToday(),
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
            'project' => $this->filled('project') ? $this->integer('project') : null,
        ];
    }
}
