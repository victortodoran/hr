<?php

namespace App\Http\Requests\Performance;

use App\Enums\Progress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing a Goal's Progress: one from the fixed list, required.
 */
class GoalProgressRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'progress' => ['required', Rule::enum(Progress::class)],
        ];
    }

    /**
     * The Progress chosen.
     */
    public function progress(): Progress
    {
        return Progress::from($this->string('progress')->value());
    }
}
