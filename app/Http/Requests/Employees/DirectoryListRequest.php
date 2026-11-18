<?php

namespace App\Http\Requests\Employees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Directory page's filter: part of a name.
 */
class DirectoryListRequest extends FormRequest
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
