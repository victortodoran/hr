<?php

namespace App\Http\Requests\WorkEntries;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Work Entries selected on the Approvals page, by ID, to decline all at
 * once, with the one reason the Employee will see on each.
 */
class DeclineWorkEntriesRequest extends SelectedWorkEntriesRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::idRules(),
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

    /**
     * The reason given for declining.
     */
    public function reason(): string
    {
        return $this->string('decline_reason')->value();
    }
}
