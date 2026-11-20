<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\SpreadsheetFormat;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class WorkEntryExportRequest extends WorkEntryListRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof Employee
            && ($actor->can('viewList', WorkEntry::class) || $this->isOwn($actor));
    }

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

    public function spreadsheetFormat(): SpreadsheetFormat
    {
        return SpreadsheetFormat::from($this->string('format')->value());
    }
}
