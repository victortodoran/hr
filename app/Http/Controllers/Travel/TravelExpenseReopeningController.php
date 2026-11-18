<?php

namespace App\Http\Controllers\Travel;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\SelectedTravelExpensesRequest;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Reopening Travel Expenses from their Travel Request's page: sending Approved
 * ones back to Pending so the Employee can correct them, after which their
 * Manager approves them again. Only a Role that grants reopening reopens
 * them; being the Manager is not enough, and nobody reopens their own.
 */
class TravelExpenseReopeningController extends Controller
{
    /**
     * Reopen the selected Travel Expenses of the Travel Request that the
     * actor may reopen: Approved ones of Employees their reopen Scope covers, never their own, sent
     * back to Pending with their approval withdrawn. One of an Employee
     * with no Manager waits for them to correct it, which Approves it again
     * at once. The rest are skipped, and the toast says how many of each.
     */
    public function store(TravelRequest $travelRequest, #[CurrentUser] Employee $actor, SelectedTravelExpensesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $reopened = TravelExpense::query()
            ->ofEmployees(Employee::query()->reopenableBy($actor, Permission::ReopenTravelExpenses))
            ->where('travel_request_id', $travelRequest->id)
            ->whereKey($ids)
            ->approved()
            ->update(['state' => TravelExpenseState::Pending, 'approved_by' => null, 'approved_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Travel Expenses reopened|{1} 1 Travel Expense reopened|[2,*] :count Travel Expenses reopened', $reopened),
            count($ids) - $reopened,
        )]);

        return back();
    }
}
