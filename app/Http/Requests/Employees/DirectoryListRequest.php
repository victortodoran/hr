<?php

namespace App\Http\Requests\Employees;

use App\Enums\DirectoryTab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Directory page's tab, filter (part of a name) and the person open
 * in a sheet over it.
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
            'person' => ['nullable', 'integer'],
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
     * The id of the Employee whose sheet is asked for, null when none is.
     */
    public function person(): ?int
    {
        return $this->filled('person') ? $this->integer('person') : null;
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
