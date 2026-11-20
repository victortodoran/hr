<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\DeclineTravelExpenses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\DeclineTravelExpensesRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TravelExpenseDeclineController extends Controller
{
    public function store(#[CurrentUser] Employee $actor, DeclineTravelExpensesRequest $request, DeclineTravelExpenses $declineTravelExpenses): RedirectResponse
    {
        $ids = $request->ids();

        $declined = $declineTravelExpenses->decline($actor, $ids, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Travel Expenses declined|{1} 1 Travel Expense declined|[2,*] :count Travel Expenses declined', $declined),
            count($ids) - $declined,
        )]);

        return back();
    }
}
