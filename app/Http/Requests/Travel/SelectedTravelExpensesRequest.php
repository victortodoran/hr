<?php

namespace App\Http\Requests\Travel;

use App\Http\Requests\WorkEntries\SelectedWorkEntriesRequest;
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
        return SelectedWorkEntriesRequest::idRules();
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
        return SelectedWorkEntriesRequest::distinctIds($this->array('ids'));
    }

    /**
     * What a bulk action did to the selection, such as "2 Travel Expenses
     * approved, 1 skipped."
     */
    public function outcome(string $done, int $skipped): string
    {
        return $skipped > 0 ? __(':done, :skipped skipped.', ['done' => $done, 'skipped' => $skipped]) : $done.'.';
    }
}
