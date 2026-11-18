<?php

namespace App\Http\Controllers\WorkEntries;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryExportRequest;
use App\Models\Employee;
use App\Support\WorkEntrySpreadsheet;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloading the Work Entries page as a file: every Work Entry matching its
 * filters, across all its pages, among those the viewer may see there.
 */
class WorkEntryExportController extends Controller
{
    /**
     * The matching Work Entries, oldest first like the page, as a CSV or
     * Excel file named after the Period.
     */
    public function show(#[CurrentUser] Employee $actor, WorkEntryExportRequest $request): StreamedResponse
    {
        return WorkEntrySpreadsheet::download($actor, $request->filters(), $request->spreadsheetFormat());
    }
}
