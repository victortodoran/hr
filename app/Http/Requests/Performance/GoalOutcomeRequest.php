<?php

namespace App\Http\Requests\Performance;

use App\Enums\Outcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Giving a Goal its Outcome: one from the fixed list, or none to clear it.
 */
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

    /**
     * The Outcome chosen, or null to clear it.
     */
    public function outcome(): ?Outcome
    {
        return $this->enum('outcome', Outcome::class);
    }
}
