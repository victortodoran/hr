<?php

namespace App\Http\Requests\Employees;

use App\Enums\DirectoryTab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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

    public function search(): string
    {
        return $this->string('search')->value();
    }

    public function person(): ?int
    {
        return $this->filled('person') ? $this->integer('person') : null;
    }

    /**
     * Falls back to the first tab the viewer may open (rule K4).
     *
     * @param  list<DirectoryTab>  $openable
     */
    public function tab(array $openable): DirectoryTab
    {
        $asked = DirectoryTab::tryFrom($this->string('tab')->value());

        return in_array($asked, $openable, true) ? $asked : $openable[0];
    }
}
