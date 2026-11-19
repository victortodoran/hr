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
     * The one Employee asked for, null when none is.
     */
    public function employeeId(): ?int
    {
        return $this->filled('employee') ? $this->integer('employee') : null;
    }

    /**
     * The state asked for, null when none is or it isn't one, such as the
     * None yet the list once had.
     */
    public function state(): ?PerformanceEvaluationState
    {
        return $this->enum('state', PerformanceEvaluationState::class);
    }
}
