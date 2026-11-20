<?php

namespace App\Http\Requests\Performance;

use App\Enums\PerformanceEvaluationState;
use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Performance Evaluations page's filters: the calendar year shown, up
 * to the next one, and the current one when none is asked for; part of an
 * Employee's name, or one Employee, as an Employee's page links here for
 * them; and a state, any state when none or an unknown one is chosen.
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
            'search' => ['nullable', 'string', 'max:255'],
            'employee' => ['nullable', 'integer'],
            'state' => ['nullable', 'string'],
        ];
    }

    /**
     * The filters asked for: the year, or the current one; the part of a
     * name, empty when none is; the state, null when none is or it isn't a
     * Performance Evaluation state; and the one Employee, null when none is.
     *
     * @return array{year: int, search: string, state: PerformanceEvaluationState|null, employee: int|null}
     */
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
