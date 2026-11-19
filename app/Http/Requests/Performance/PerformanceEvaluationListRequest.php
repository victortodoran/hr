<?php

namespace App\Http\Requests\Performance;

use App\Enums\PerformanceEvaluationListState;
use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Performance Evaluations page's filters: the calendar year shown, up
 * to the next one, and the current one when none is asked for; part of an
 * Employee's name; and a state, any state when none is chosen.
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
            'state' => ['nullable', Rule::enum(PerformanceEvaluationListState::class)],
        ];
    }

    /**
     * The year asked for, or the current one.
     */
    public function year(): int
    {
        return $this->filled('year') ? $this->integer('year') : today()->year;
    }

    /**
     * The part of a name asked for, empty when none is.
     */
    public function search(): string
    {
        return $this->string('search')->value();
    }

    /**
     * The state asked for, null when none is.
     */
    public function state(): ?PerformanceEvaluationListState
    {
        return $this->filled('state') ? $this->enum('state', PerformanceEvaluationListState::class) : null;
    }
}
