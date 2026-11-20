<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\SpreadsheetFormat;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Exporting the Work Entries page, or an Employee's own Work page: its
 * filters as the page resolved them, so the Employee is always given, one
 * or "all", and the file's format.
 */
class WorkEntryExportRequest extends WorkEntryListRequest
{
    /**
     * Someone who may open the Work Entries page exports what it lists;
     * anyone else, only their own Work Entries, as their Work page shows.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof Employee
            && ($actor->can('viewList', WorkEntry::class) || $this->isOwn($actor));
    }

    /**
     * Whether the export asks for the viewer's own Work Entries only.
     */
    public function isOwn(Employee $actor): bool
    {
        return $this->query('employee') === (string) $actor->id;
    }

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
        return SpreadsheetFormat::from($this->string('format')->value());
    }
}
