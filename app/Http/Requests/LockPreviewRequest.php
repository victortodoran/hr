<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Work Entries about to be locked, with the Work Entries filters they were
 * selected under, so Pending ones matching the same filters can be counted.
 */
class LockPreviewRequest extends WorkEntryFiltersRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...parent::rules(), ...SelectedWorkEntriesRequest::idRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return SelectedWorkEntriesRequest::idMessages();
    }

    /**
     * The IDs of the selected Work Entries, each once.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        return SelectedWorkEntriesRequest::distinctIds($this->array('ids'));
    }
}
