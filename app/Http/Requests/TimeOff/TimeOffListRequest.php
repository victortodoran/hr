<?php

namespace App\Http\Requests\TimeOff;

use App\Enums\TimeOffState;
use App\Support\Period;
use App\Support\PeriodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimeOffListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...PeriodRules::rules(),
            'employee' => ['nullable', 'regex:/^(all|[0-9]+)$/'],
            'state' => ['nullable', Rule::enum(TimeOffState::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => PeriodRules::checkCustomLength($this, $validator)];
    }

    /** @return array{period: Period, employee: int|'all', state: string|null} */
    public function filters(): array
    {
        return [
            'period' => Period::fromQuery($this->query()) ?? Period::month(today()->format('Y-m')),
            'employee' => $this->filled('employee') && $this->input('employee') !== 'all' ? $this->integer('employee') : 'all',
            'state' => $this->filled('state') ? $this->string('state')->value() : null,
        ];
    }
}
