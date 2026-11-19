<?php

namespace App\Http\Requests\Performance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The New Performance Evaluation page's query string: the year and the
 * Employee to start with, as the Create on a None yet row of the
 * Performance Evaluations page links to it, both optional.
 */
class NewPerformanceEvaluationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * The year asked for, null when none is.
     */
    public function year(): ?int
    {
        return $this->filled('year') ? $this->integer('year') : null;
    }

    /**
     * The id of the Employee asked for, null when none is.
     */
    public function employeeId(): ?int
    {
        return $this->filled('employee_id') ? $this->integer('employee_id') : null;
    }
}
