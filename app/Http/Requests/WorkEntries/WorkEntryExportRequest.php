<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\SpreadsheetFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Exporting the Work Entries page: its filters as the page resolved them,
 * so the Employee is always given, one or "all", and the file's format.
 */
class WorkEntryExportRequest extends WorkEntryListRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'employee' => ['required', 'regex:/^(all|[0-9]+)$/'],
            'format' => ['required', Rule::enum(SpreadsheetFormat::class)],
        ];
    }

    /**
     * The format the file is written in.
     */
    public function spreadsheetFormat(): SpreadsheetFormat
    {
        return $this->enum('format', SpreadsheetFormat::class) ?? SpreadsheetFormat::Csv; // @pest-mutate-ignore (validation requires a known format)
    }
}
