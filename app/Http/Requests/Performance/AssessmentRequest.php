<?php

namespace App\Http\Requests\Performance;

use App\Enums\Outcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Manager's assessment of a Performance Evaluation as the form sends
 * it: the Outcome of the whole and a written summary, either still empty
 * while it is being written. Each Goal's Outcome is given on its own.
 */
class AssessmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['nullable', Rule::enum(Outcome::class)],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
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
