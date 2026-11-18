<?php

namespace App\Http\Requests\Travel;

use App\Support\SelectedIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Travel Expenses selected on the Approvals page or a Travel Request's
 * page, by ID, for approving or reopening them all at once.
 */
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

    /**
     * The IDs of the selected Travel Expenses, each once.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        return SelectedIds::distinct($this->array('ids'));
    }

    /**
     * What a bulk action did to the selection, such as "2 Travel Expenses
     * approved, 1 skipped."
     */
    public function outcome(string $done, int $skipped): string
    {
        return SelectedIds::outcome($done, $skipped);
    }
}
