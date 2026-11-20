<?php

namespace App\Http\Requests\Performance;

use App\Enums\Outcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompletionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::enum(Outcome::class)],
        ];
    }

    public function outcome(): Outcome
    {
        return Outcome::from($this->string('outcome')->value());
    }
}
