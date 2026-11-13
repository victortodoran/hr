<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for a list of Work Entries: an Employee, a Project and a date
 * range, each optional.
 */
class WorkEntryFiltersRequest extends FormRequest
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
        ];
    }

    /**
     * The filters, null where none is set.
     *
     * @return array{employee: int|null, project: int|null, from: string|null, to: string|null}
     */
    public function filters(): array
    {
        return [
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
            'project' => $this->filled('project') ? $this->integer('project') : null,
            'from' => $this->filled('from') ? $this->string('from')->value() : null,
            'to' => $this->filled('to') ? $this->string('to')->value() : null,
        ];
    }
}
