<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\WorkEntryState;
use App\Support\Period;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

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

    /** @return array{period: Period, employee: int|'all'|null, project: int|null, state: string|null} */
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
