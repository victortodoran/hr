<?php

namespace App\Http\Requests\TimeOff;

use App\Enums\TimeOffState;
use App\Support\Period;
use App\Support\PeriodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Time Off page's filters, across Employees: a Period, a week, a month
 * or a custom period of at most three months, in the future too as Time
 * Off is planned ahead; an Employee, or "all" for any Employee; and a
 * state, any state when none is chosen.
 */
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

    /**
     * The filters asked for: the current month when no Period is, and any
     * Employee ("all") when no one Employee is; null where no state is set.
     *
     * @return array{period: Period, employee: int|'all', state: string|null}
     */
    public function filters(): array
    {
        return [
            'period' => Period::fromQuery($this->query()) ?? Period::month(today()->format('Y-m')),
            'employee' => $this->filled('employee') && $this->input('employee') !== 'all' ? $this->integer('employee') : 'all',
            'state' => $this->filled('state') ? $this->string('state')->value() : null,
        ];
    }
}
