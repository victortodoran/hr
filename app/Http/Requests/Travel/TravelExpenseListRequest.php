<?php

namespace App\Http\Requests\Travel;

use App\Enums\TravelExpenseState;
use App\Http\Requests\WorkEntries\WorkEntryFiltersRequest;
use App\Support\Period;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The Travel Expenses page's filters, as on the Work Entries page: the
 * Period they were paid in, an Employee or "all" for any Employee, a
 * Project and a state, any state when none is chosen.
 */
class TravelExpenseListRequest extends WorkEntryFiltersRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'employee' => ['nullable', 'regex:/^(all|[0-9]+)$/'],
            'state' => ['nullable', Rule::enum(TravelExpenseState::class)],
        ];
    }

    /**
     * The filters asked for, null where none is set; the Employee is "all"
     * when any Employee is asked for.
     *
     * @return array{period: Period, employee: int|'all'|null, project: int|null, state: string|null}
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
