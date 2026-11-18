<?php

namespace App\Http\Requests\Travel;

use App\Enums\TravelRequestState;
use App\Http\Requests\WorkEntries\WorkEntryPeriodRequest;
use App\Support\Period;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Travel Requests page's filters: a Period, a week, a month or a
 * custom period of at most three months, in the future too as travel is
 * planned ahead; an Employee, or "all" for any Employee; a Project and a
 * state, any state when none is chosen.
 */
class TravelRequestListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...WorkEntryPeriodRequest::periodRules(),
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
        return [fn (Validator $validator) => WorkEntryPeriodRequest::checkCustomLength($this, $validator)];
    }

    /**
     * The filters asked for: the current month when no Period is, and any
     * Employee ("all") when no one Employee is; null where none is set.
     *
     * @return array{period: Period, employee: int|'all', project: int|null, state: string|null}
     */
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
