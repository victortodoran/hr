<?php

namespace App\Http\Controllers\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelExpenseExportRequest;
use App\Models\Employee;
use App\Pages\Travel\TravelRequestListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** For paying Approved Travel Expenses back (ADR 0007). */
class TravelExpenseExportController extends Controller
{
    public function show(#[CurrentUser] Employee $actor, TravelExpenseExportRequest $request): StreamedResponse
    {
        return TravelRequestListPage::download($actor, $request->exportFilters(), $request->spreadsheetFormat());
    }
}
