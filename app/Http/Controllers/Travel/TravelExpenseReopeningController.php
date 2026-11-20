<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\ReopenTravelExpenses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\SelectedTravelExpensesRequest;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TravelExpenseReopeningController extends Controller
{
    public function store(TravelRequest $travelRequest, #[CurrentUser] Employee $actor, SelectedTravelExpensesRequest $request, ReopenTravelExpenses $reopenTravelExpenses): RedirectResponse
    {
        $ids = $request->ids();

        $reopened = $reopenTravelExpenses->reopen($actor, $travelRequest, $ids);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Travel Expenses reopened|{1} 1 Travel Expense reopened|[2,*] :count Travel Expenses reopened', $reopened),
            count($ids) - $reopened,
        )]);

        return back();
    }
}
