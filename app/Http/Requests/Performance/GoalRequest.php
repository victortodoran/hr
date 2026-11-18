<?php

namespace App\Http\Requests\Performance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Giving or changing a Goal: its title and description, both required,
 * and, when changed from the Goal's own page, `from=goal` to lead back there.
 */
class GoalRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'from' => ['nullable', Rule::in(['goal'])],
        ];
    }

    /**
     * The Goal as it is stored.
     *
     * @return array{title: string, description: string}
     */
    public function goal(): array
    {
        return [
            'title' => $this->string('title')->trim()->value(),
            'description' => $this->string('description')->trim()->value(),
        ];
    }

    /**
     * Whether saving leads back to the Goal's own page rather than the
     * Performance Evaluation's.
     */
    public function returnsToGoal(): bool
    {
        return $this->input('from') === 'goal';
    }
}
