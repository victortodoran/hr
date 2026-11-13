<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for a month of Work Entries: the month, as YYYY-MM, and an
 * Employee and a Project, each optional.
 */
class WorkEntryFiltersRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'employee' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
        ];
    }

    /**
     * The filters, null where none is set. The month is the current one when
     * none is asked for, or one after it, as Work Entries are never in the
     * future.
     *
     * @return array{month: string, employee: int|null, project: int|null}
     */
    public function filters(): array
    {
        $current = now()->format('Y-m');
        $month = $this->filled('month') ? $this->string('month')->value() : $current;

        return [
            'month' => min($month, $current),
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
            'project' => $this->filled('project') ? $this->integer('project') : null,
        ];
    }
}
