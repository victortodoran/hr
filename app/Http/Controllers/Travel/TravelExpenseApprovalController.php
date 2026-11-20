<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\ApproveTravelExpenses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\SelectedTravelExpensesRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TravelExpenseApprovalController extends Controller
{
    /** Expenses the actor may not approve are skipped, so resending an approval changes nothing more (ADR 0005). */
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
