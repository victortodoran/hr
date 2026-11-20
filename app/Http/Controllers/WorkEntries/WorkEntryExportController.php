<?php

namespace App\Http\Controllers\WorkEntries;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryExportRequest;
use App\Models\Employee;
use App\Pages\WorkEntries\WorkEntryListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkEntryExportController extends Controller
{
    public function show(#[CurrentUser] Employee $actor, WorkEntryExportRequest $request): StreamedResponse
    {
        return WorkEntryListPage::download($actor, $request->filters(), $request->spreadsheetFormat(), ownOnly: $request->isOwn($actor));
    }
}
