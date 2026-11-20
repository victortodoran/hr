<?php

namespace App\Http\Requests\WorkEntries;

use App\Support\SelectedIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectedWorkEntriesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return SelectedIds::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::idMessages();
    }

    /** @return array<int, int> */
    public function ids(): array
    {
        return SelectedIds::distinct($this->array('ids'));
    }

    public function outcome(string $done, int $skipped): string
    {
        return SelectedIds::outcome($done, $skipped);
    }

    /**
     * @return array<string, string>
     */
    public static function idMessages(): array
    {
        $message = __('Select at least one Work Entry.');

        return ['ids.required' => $message, 'ids.list' => $message];
    }
}
