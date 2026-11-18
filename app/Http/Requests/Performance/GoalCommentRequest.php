<?php

namespace App\Http\Requests\Performance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Writing a Comment on a Goal: plain text, required.
 */
class GoalCommentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * The Comment's text as it is stored.
     */
    public function body(): string
    {
        return $this->string('body')->trim()->value();
    }
}
