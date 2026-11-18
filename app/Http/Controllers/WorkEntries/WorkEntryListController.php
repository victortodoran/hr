<?php

namespace App\Http\Controllers\WorkEntries;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryListRequest;
use App\Models\Employee;
use App\Pages\WorkEntries\WorkEntryListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Work Entries page: Work Entries of the Employees the viewer may see,
 * one Period and one Employee at a time, or every Employee at once, with
 * reopening many of them at once. Who is listed comes from the viewer's
 * view, manage and reopen Scopes. Reopening is done by the
 * ReopeningController; approving happens on the Approvals page.
 */
class WorkEntryListController extends Controller
{
    /**
     * The Work Entries matching the filters, with the Employees and Projects
     * to choose from (see WorkEntryListPage).
     */
    public function index(#[CurrentUser] Employee $actor, WorkEntryListRequest $request): Response
    {
        return Inertia::render('work-entries/List', WorkEntryListPage::props($actor, $request->filters()));
    }
}
