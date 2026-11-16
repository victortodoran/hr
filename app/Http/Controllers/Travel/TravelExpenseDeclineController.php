<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelExpenseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\DeclineTravelExpensesRequest;
use App\Models\Employee;
use App\Models\TravelExpense;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Lets an effective Manager decline Pending Travel Expenses from the
 * Approvals page, one or several at once with one reason.
 */
class TravelExpenseDeclineController extends Controller
{
    /**
     * Decline the selected Travel Expenses the actor may decline, the same
     * ones they may approve: Pending ones of Employees whose effective
     * Manager they are. Each records who declined it, when and why, and the
     * Employee sees the reason on their Travel page. The rest are skipped,
     * and the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, DeclineTravelExpensesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $declined = TravelExpense::query()
            ->ofEmployees(Employee::query()->effectivelyManagedBy($actor))
            ->whereKey($ids)
            ->pending()
            ->update([
                'state' => TravelExpenseState::Declined,
                'declined_by' => $actor->id,
                'declined_at' => now(),
                'decline_reason' => $request->reason(),
            ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Travel Expenses declined|{1} 1 Travel Expense declined|[2,*] :count Travel Expenses declined', $declined),
            count($ids) - $declined,
        )]);

        return back();
    }
}
