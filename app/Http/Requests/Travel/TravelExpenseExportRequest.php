<?php

namespace App\Http\Requests\Travel;

use App\Enums\SpreadsheetFormat;
use App\Support\Period;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TravelExpenseExportRequest extends TravelRequestListRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::enum(SpreadsheetFormat::class)],
        ];
    }

    /**
     * The page's state filter is about Travel Requests, so the file leaves it out.
     *
     * @return array{period: Period, employee: int|'all', project: int|null}
     */
    public function exportFilters(): array
    {
        $filters = $this->filters();

        return ['period' => $filters['period'], 'employee' => $filters['employee'], 'project' => $filters['project']];
    }

    public function spreadsheetFormat(): SpreadsheetFormat
    {
        return SpreadsheetFormat::from($this->string('format')->value());
    }
}
