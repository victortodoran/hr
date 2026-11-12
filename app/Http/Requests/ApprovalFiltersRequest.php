<?php

namespace App\Http\Requests;

use App\Enums\WorkEntryState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters on the Approvals page: an Employee, a Project, a date range
 * and a state, each optional.
 */
class ApprovalFiltersRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'state' => ['nullable', Rule::enum(WorkEntryState::class)],
        ];
    }

    /**
     * The filters, null where none is set, and the given state when no state
     * is chosen.
     *
     * @return array{employee: int|null, project: int|null, from: string|null, to: string|null, state: string}
     */
    public function filters(WorkEntryState $state): array
    {
        return [
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
            'project' => $this->filled('project') ? $this->integer('project') : null,
            'from' => $this->filled('from') ? $this->string('from')->value() : null,
            'to' => $this->filled('to') ? $this->string('to')->value() : null,
            'state' => $this->filled('state') ? $this->string('state')->value() : $state->value,
        ];
    }
}
