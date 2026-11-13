<?php

namespace App\Http\Requests;

use App\Enums\WorkEntryState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The Work Entries page's filters: an Employee, a Project, a date range and
 * a state, each optional, every state when none is chosen.
 */
class WorkEntryListRequest extends WorkEntryFiltersRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'state' => ['nullable', Rule::enum(WorkEntryState::class)]];
    }

    /**
     * The filters, null where none is set.
     *
     * @return array{employee: int|null, project: int|null, from: string|null, to: string|null, state: string|null}
     */
    public function filters(): array
    {
        return [...parent::filters(), 'state' => $this->filled('state') ? $this->string('state')->value() : null];
    }
}
