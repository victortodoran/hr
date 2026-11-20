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

/**
 * The Approvals page, an inbox of what waits for the viewer's decision as an
 * effective Manager, in two tabs. Work Entries: the Pending Work Entries of
 * the Employees whose effective Manager the viewer is, one Employee and one
 * Project at a time, and approving many of them at once, or declining them
 * with the WorkEntryDeclineController. Nobody approves or declines their own
 * Work Entries.
 * Reopening happens on the Work Entries page. Time Off: the
 * Pending Time Off the viewer decides, answered by the
 * TimeOffApprovalController and TimeOffDeclineController. Travel Requests:
 * the Pending Travel Requests the viewer decides, answered by the
 * TravelRequestApprovalController and TravelRequestDeclineController.
 * Travel Expenses: the Pending Travel Expenses the viewer decides, answered
 * several at once by the TravelExpenseApprovalController and
 * TravelExpenseDeclineController.
 */
class ApprovalController extends Controller
{
    /**
     * The open tab, Work Entries unless another is asked for, with how
     * many Work Entries, Time Off, Travel Requests and Travel Expenses wait
     * for the viewer's decision. Only the open tab's list is loaded.
     */
    public function index(#[CurrentUser] Employee $actor, ApprovalFiltersRequest $request): Response
    {
        return Inertia::render('approvals/Index', ApprovalsPage::props($actor, $request->tab(), $request->filters()));
    }

    /**
     * Approve the selected Work Entries the actor may approve: Pending ones
     * of Employees whose effective Manager they are. The rest are skipped,
     * and the toast says how many of each.
     */
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
