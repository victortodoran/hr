<?php

namespace App\Http\Requests\Employees;

use App\Enums\DirectoryTab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Directory page's tab and filter: part of a name.
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

    /**
     * The tab asked for, when the viewer may open it, else the first one
     * they may (rule K4).
     *
     * @param  list<DirectoryTab>  $openable
     */
    public function tab(array $openable): DirectoryTab
    {
        $asked = DirectoryTab::tryFrom($this->string('tab')->value());

        return in_array($asked, $openable, true) ? $asked : $openable[0];
    }
}
