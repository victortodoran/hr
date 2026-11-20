<?php

namespace App\Http\Requests\Search;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Projects and Clients pages' filter, and the command menu's search:
 * part of a name.
 */
class NameSearchRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The part of a name asked for, empty when none is.
     */
    public function search(): string
    {
        return $this->string('search')->value();
    }
}
