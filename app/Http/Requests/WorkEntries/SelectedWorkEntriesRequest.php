<?php

namespace App\Http\Requests\WorkEntries;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Work Entries selected on the Approvals page, by ID, for approving,
 * reopening them all at once.
 */
class SelectedWorkEntriesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::idRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::idMessages();
    }

    /**
     * The IDs of the selected Work Entries, each once.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        return self::distinctIds($this->array('ids'));
    }

    /**
     * What a bulk action did to the selection, such as "10 Work Entries
     * approved, 5 skipped."
     */
    public function outcome(string $done, int $skipped): string
    {
        return $skipped > 0 ? __(':done, :skipped skipped.', ['done' => $done, 'skipped' => $skipped]) : $done.'.';
    }

    /**
     * Validated Work Entry IDs, which arrive as strings in a query string, as
     * whole numbers, each once.
     *
     * @param  array<mixed>  $ids
     * @return array<int, int>
     */
    public static function distinctIds(array $ids): array
    {
        return collect($ids)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all(); // @pest-mutate-ignore (the int return type already turns the validated numeric strings into numbers)
    }

    /**
     * A non-empty list of Work Entry IDs.
     *
     * @return array<string, array<int, string>>
     */
    public static function idRules(): array
    {
        return [
            'ids' => ['required', 'list'],
            'ids.*' => ['integer'],
        ];
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
