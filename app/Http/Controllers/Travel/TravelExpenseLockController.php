<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelExpenseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\SelectedTravelExpensesRequest;
use App\Models\Employee;
use App\Models\TravelExpense;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Locking Travel Expenses for good from the Travel Expenses page, for
 * example once finance has paid the Employees back. Locking applies
 * company-wide, the locker's own Travel Expenses included, only ever to
 * Approved ones, and closes no period (ADR 0005).
 */
class TravelExpenseLockController extends Controller
{
    /**
     * Lock the selected Travel Expenses that are still Approved, recording
     * who locked them and when. Anything else, such as one reopened since
     * the locker saw it or one already locked, is skipped, so sending the
     * same lock twice changes nothing more, and the toast says how many of
     * each.
     */
    public function store(#[CurrentUser] Employee $actor, SelectedTravelExpensesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $locked = TravelExpense::query()
            ->whereKey($ids)
            ->approved()
            ->update(['state' => TravelExpenseState::Locked, 'locked_by' => $actor->id, 'locked_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Travel Expenses locked|{1} 1 Travel Expense locked|[2,*] :count Travel Expenses locked', $locked),
            count($ids) - $locked,
        )]);

        return back();
    }
}
