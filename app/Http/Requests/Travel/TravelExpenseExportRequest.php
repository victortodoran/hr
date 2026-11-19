<?php

namespace App\Http\Requests\Travel;

use App\Enums\SpreadsheetFormat;
use App\Support\Period;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Exporting the Approved Travel Expenses from the Travel Requests page:
 * its Period, Employee and Project, and the file's format. The page's
 * state filter is about Travel Requests, so the file leaves it out.
 */
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
     * The filters asked for, as on the page but without a state.
     *
     * @return array{period: Period, employee: int|'all', project: int|null}
     */
    public function exportFilters(): array
    {
        $filters = $this->filters();

        return ['period' => $filters['period'], 'employee' => $filters['employee'], 'project' => $filters['project']];
    }

    /**
     * The format the file is written in.
     */
    public function spreadsheetFormat(): SpreadsheetFormat
    {
        return SpreadsheetFormat::from($this->string('format')->value());
    }
}
