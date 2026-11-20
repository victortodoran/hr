<?php

namespace App\Support;

final class SelectedIds
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'ids' => ['required', 'list'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * IDs arrive as strings in a query string.
     *
     * @param  array<mixed>  $ids
     * @return array<int, int>
     */
    public static function distinct(array $ids): array
    {
        return collect($ids)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all(); // @pest-mutate-ignore (the int return type already turns the validated numeric strings into numbers)
    }

    public static function outcome(string $done, int $skipped): string
    {
        return $skipped > 0 ? __(':done, :skipped skipped.', ['done' => $done, 'skipped' => $skipped]) : $done.'.';
    }
}
