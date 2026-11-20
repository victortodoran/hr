<?php

namespace App\Http\Requests\Performance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    /** @return array{title: string, description: string} */
    public function goal(): array
    {
        return [
            'title' => $this->string('title')->trim()->value(),
            'description' => $this->string('description')->trim()->value(),
        ];
    }
}
