<?php

namespace App\Http\Controllers\WorkEntries;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\WorkEntryListRequest;
use App\Models\Employee;
use App\Pages\WorkEntries\WorkEntryListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class WorkEntryListController extends Controller
{
    public function index(#[CurrentUser] Employee $actor, WorkEntryListRequest $request): Response
    {
        return Inertia::render('work-entries/List', WorkEntryListPage::props($actor, $request->filters()));
    }
}
