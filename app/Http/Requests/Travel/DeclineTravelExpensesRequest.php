<?php

namespace App\Http\Requests\Travel;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Travel Expenses selected on the Approvals page, by ID, to decline all
 * at once, with the one reason the Employee will see on each.
 */
class DeclineTravelExpensesRequest extends SelectedTravelExpensesRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'decline_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
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
