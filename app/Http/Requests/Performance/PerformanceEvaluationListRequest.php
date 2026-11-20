<?php

namespace App\Http\Requests\Performance;

use App\Enums\PerformanceEvaluationState;
use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PerformanceEvaluationListRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.max(PerformanceEvaluation::creatableYears())],
            'search' => ['nullable', 'string', 'max:255'],
            'employee' => ['nullable', 'integer'],
            'state' => ['nullable', 'string'],
        ];
    }

    /** @return array{year: int, search: string, state: PerformanceEvaluationState|null, employee: int|null} */
    public function filters(): array
    {
        return [
            'year' => $this->filled('year') ? $this->integer('year') : today()->year,
            'search' => $this->string('search')->value(),
            'state' => $this->enum('state', PerformanceEvaluationState::class),
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
        ];
    }
}
