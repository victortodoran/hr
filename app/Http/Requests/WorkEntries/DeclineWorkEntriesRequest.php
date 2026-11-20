<?php

namespace App\Http\Requests\WorkEntries;

use App\Support\SelectedIds;
use Illuminate\Contracts\Validation\ValidationRule;

class DeclineWorkEntriesRequest extends SelectedWorkEntriesRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...SelectedIds::rules(),
            'decline_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...self::idMessages(),
            'decline_reason.required' => __('Give a reason for declining.'),
        ];
    }

    public function reason(): string
    {
        return $this->string('decline_reason')->value();
    }
}
