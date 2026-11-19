<?php

namespace App\Http\Requests\Performance;

use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Performance Evaluations page's filter: the calendar year shown, up
 * to the next one, and the current one when none is asked for.
 */
class PerformanceEvaluationListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.max(PerformanceEvaluation::creatableYears())],
        ];
    }

    /**
     * The year asked for, or the current one.
     */
    public function year(): int
    {
        return $this->filled('year') ? $this->integer('year') : today()->year;
    }
}
