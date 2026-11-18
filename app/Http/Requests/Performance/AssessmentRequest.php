<?php

namespace App\Http\Requests\Performance;

use App\Enums\Outcome;
use App\Models\PerformanceEvaluation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Manager's assessment of a Performance Evaluation as the form sends
 * it: an Outcome for each of its Goals, one for the whole and a written
 * summary, any of them still empty while it is being written.
 */
class AssessmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $evaluation = $this->route('performanceEvaluation');

        return [
            'goals' => ['present', 'array'],
            'goals.*.id' => ['required', 'integer', 'distinct', Rule::exists('goals', 'id')->where('performance_evaluation_id', $evaluation instanceof PerformanceEvaluation ? $evaluation->id : null)],
            'goals.*.outcome' => ['nullable', Rule::enum(Outcome::class)],
            'outcome' => ['nullable', Rule::enum(Outcome::class)],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Each Goal's Outcome as given, by Goal ID, null where left empty.
     *
     * @return array<int, Outcome|null>
     */
    public function goalOutcomes(): array
    {
        return collect($this->array('goals'))
            ->mapWithKeys(fn (array $goal): array => [(int) $goal['id'] => isset($goal['outcome']) ? Outcome::from($goal['outcome']) : null])
            ->all();
    }

    /**
     * The Outcome of the whole, or null while none is given.
     */
    public function outcome(): ?Outcome
    {
        return $this->enum('outcome', Outcome::class);
    }

    /**
     * The summary, or null while it is empty.
     */
    public function summary(): ?string
    {
        $summary = $this->string('summary')->trim()->value();

        return $summary === '' ? null : $summary;
    }
}
