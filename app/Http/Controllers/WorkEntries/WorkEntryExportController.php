<?php

namespace App\Http\Controllers\WorkEntries;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryExportRequest;
use App\Models\Employee;
use App\Models\WorkEntry;
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
        $filters = $request->filters();
        $entries = WorkEntry::query()
            ->listedFor($actor, $filters)
            ->with(['assignment.employee:id,name', 'assignment.project:id,name,client_id', 'assignment.project.client:id,name', 'approver:id,name', 'locker:id,name', 'decliner:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same rows)
            ->orderBy('date')
            ->orderBy('id');

        return WorkEntrySpreadsheet::download($entries, $request->spreadsheetFormat(), $filters['period']);
    }
}
