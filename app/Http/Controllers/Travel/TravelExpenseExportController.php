<?php

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelExpenseExportRequest;
use App\Models\Employee;
use App\Pages\Travel\TravelRequestListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloading the Approved Travel Expenses from the Travel Requests page,
 * for paying them back (ADR 0007): those paid in its Period, of the
 * Employees the viewer may see travel of, narrowed by its Employee and
 * Project.
 */
class TravelExpenseExportController extends Controller
{
    /**
     * The matching Approved Travel Expenses, by date paid, as a CSV or
     * Excel file named after the Period.
     */
    public function show(#[CurrentUser] Employee $actor, TravelExpenseExportRequest $request): StreamedResponse
    {
        return TravelRequestListPage::download($actor, $request->exportFilters(), $request->spreadsheetFormat());
    }
}
