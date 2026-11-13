<?php

namespace App\Http\Requests;

use App\Enums\WorkEntryState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The Work Entries page's filters: a month, an Employee or "all" for any
 * Employee, a Project and a state, any state when none is chosen.
 */
class WorkEntryListRequest extends WorkEntryFiltersRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'employee' => ['nullable', 'regex:/^(all|[0-9]+)$/'],
            'state' => ['nullable', Rule::enum(WorkEntryState::class)],
        ];
    }

    /**
     * The filters asked for, null where none is set; the Employee is "all"
     * when any Employee is asked for.
     *
     * @return array{month: string, employee: int|'all'|null, project: int|null, state: string|null}
     */
    public function filters(): array
    {
        $filters = parent::filters();

        return [
            ...$filters,
            'employee' => $this->input('employee') === 'all' ? 'all' : $filters['employee'],
            'state' => $this->filled('state') ? $this->string('state')->value() : null,
        ];
    }
}
