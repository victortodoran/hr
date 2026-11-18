<?php

namespace App\Support;

/**
 * Rows ticked on a list for a bulk action, such as approving or reopening
 * Work Entries or Travel Expenses, sent as a list of IDs.
 */
final class SelectedIds
{
    /**
     * A non-empty list of IDs.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'ids' => ['required', 'list'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * Validated IDs, which arrive as strings in a query string, as whole
     * numbers, each once.
     *
     * @param  array<mixed>  $ids
     * @return array<int, int>
     */
    public static function distinct(array $ids): array
    {
        return collect($ids)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all(); // @pest-mutate-ignore (the int return type already turns the validated numeric strings into numbers)
    }

    /**
     * What a bulk action did to the selection, such as "10 Work Entries
     * approved, 5 skipped."
     */
    public static function outcome(string $done, int $skipped): string
    {
        return $skipped > 0 ? __(':done, :skipped skipped.', ['done' => $done, 'skipped' => $skipped]) : $done.'.';
    }
}
