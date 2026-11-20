<?php

namespace App\Http\Requests\WorkEntries;

use App\Support\Period;
use App\Support\PeriodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Work Entries are never in the future, so the Period never runs past today.
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
