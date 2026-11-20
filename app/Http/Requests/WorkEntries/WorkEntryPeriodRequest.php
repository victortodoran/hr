<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\WorkEntriesTab;
use App\Models\Employee;
use App\Support\Period;
use App\Support\PeriodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => PeriodRules::checkCustomLength($this, $validator)];
    }

    public function tab(): WorkEntriesTab
    {
        return WorkEntriesTab::tryFrom($this->string('tab')->value()) ?? WorkEntriesTab::List;
    }

    public function period(): Period
    {
        $period = Period::fromQuery($this->query());

        if ($this->tab() === WorkEntriesTab::Calendar) {
            return Period::month(($period === null ? today() : $period->from)->format('Y-m'));
        }

        return $period ?? Period::week(today());
    }

    public function employee(Employee $viewer): ?Employee
    {
        return $this->filled('employee') ? Employee::query()->find($this->integer('employee')) : $viewer;
    }
}
