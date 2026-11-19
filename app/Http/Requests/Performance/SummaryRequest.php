<?php

namespace App\Http\Requests\Performance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Manager's summary of a Performance Evaluation as written so far,
 * empty to clear it.
 */
class SummaryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => ['present', 'nullable', 'string', 'max:5000'],
        ];
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
