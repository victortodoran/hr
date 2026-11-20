<?php

namespace App\Http\Requests\Travel;

use App\Enums\TravelRequestState;
use App\Support\Period;
use App\Support\PeriodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TravelRequestListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...PeriodRules::rules(),
            'employee' => ['nullable', 'regex:/^(all|[0-9]+)$/'],
            'project' => ['nullable', 'integer'],
            'state' => ['nullable', Rule::enum(TravelRequestState::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => PeriodRules::checkCustomLength($this, $validator)];
    }

    /** @return array{period: Period, employee: int|'all', project: int|null, state: string|null} */
    public function filters(): array
    {
        return [
            'period' => Period::fromQuery($this->query()) ?? Period::month(today()->format('Y-m')),
            'employee' => $this->filled('employee') && $this->input('employee') !== 'all' ? $this->integer('employee') : 'all',
            'project' => $this->filled('project') ? $this->integer('project') : null,
            'state' => $this->filled('state') ? $this->string('state')->value() : null,
        ];
    }
}
