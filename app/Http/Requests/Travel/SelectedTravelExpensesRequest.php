<?php

namespace App\Http\Requests\Travel;

use App\Support\SelectedIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectedTravelExpensesRequest extends FormRequest
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
        $message = __('Select at least one Travel Expense.');

        return ['ids.required' => $message, 'ids.list' => $message];
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
}
