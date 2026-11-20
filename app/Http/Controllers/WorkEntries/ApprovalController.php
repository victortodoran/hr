<?php

namespace App\Http\Controllers\WorkEntries;

use App\Actions\WorkEntries\ApproveWorkEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\ApprovalFiltersRequest;
use App\Http\Requests\WorkEntries\SelectedWorkEntriesRequest;
use App\Models\Employee;
use App\Pages\WorkEntries\ApprovalsPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(#[CurrentUser] Employee $actor, ApprovalFiltersRequest $request): Response
    {
        return Inertia::render('approvals/Index', ApprovalsPage::props($actor, $request->tab(), $request->filters()));
    }

    public function store(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request, ApproveWorkEntries $approveWorkEntries): RedirectResponse
    {
        $ids = $request->ids();

        $approved = $approveWorkEntries->approve($actor, $ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries approved|{1} 1 Work Entry approved|[2,*] :count Work Entries approved', $approved),
            count($ids) - $approved,
        )]);

        return back();
    }
}
