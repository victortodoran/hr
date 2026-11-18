<?php

namespace App\Http\Controllers\Travel;

use App\Actions\ApproveTravelExpenses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\SelectedTravelExpensesRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an effective Manager approve Pending Travel Expenses from the
 * Approvals page, one or several at once.
 */
class TravelExpenseApprovalController extends Controller
{
    /**
     * Approve the selected Travel Expenses the actor may approve: Pending
     * ones of Employees whose effective Manager they are. Each records who
     * approved it and when, in its own right (ADR 0005). The rest are
     * skipped, so sending the same approval twice changes nothing more, and
     * the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, SelectedTravelExpensesRequest $request, ApproveTravelExpenses $approveTravelExpenses): RedirectResponse
    {
        $ids = $request->ids();

        $approved = $approveTravelExpenses->approve($actor, $ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Travel Expenses approved|{1} 1 Travel Expense approved|[2,*] :count Travel Expenses approved', $approved),
            count($ids) - $approved,
        )]);

        return back();
    }
}
