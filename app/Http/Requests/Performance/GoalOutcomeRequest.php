<?php

namespace App\Http\Requests\Performance;

use App\Enums\Outcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoalOutcomeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['present', 'nullable', Rule::enum(Outcome::class)],
        ];
    }

    public function outcome(): ?Outcome
    {
        return $this->enum('outcome', Outcome::class);
    }
}
