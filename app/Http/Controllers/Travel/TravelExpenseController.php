<?php

namespace App\Http\Controllers\Travel;

use App\Actions\Travel\ChangeTravelExpense;
use App\Actions\Travel\DeleteTravelExpense;
use App\Actions\Travel\RecordTravelExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelExpenseRequest;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Pages\Travel\TravelExpenseFormPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class TravelExpenseController extends Controller
{
    /** The form opens as a sheet over this page (rule L14), so it has a URL of its own. */
    public function create(TravelRequest $travelRequest, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', TravelExpenseFormPage::create($travelRequest, $actor));
    }

    public function edit(TravelRequest $travelRequest, TravelExpense $travelExpense, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', TravelExpenseFormPage::edit($travelRequest, $travelExpense, $actor));
    }

    public function store(TravelExpenseRequest $request, TravelRequest $travelRequest, #[CurrentUser] Employee $actor, RecordTravelExpense $recordTravelExpense): RedirectResponse
    {
        $receipt = $request->receipt() ?? throw new LogicException('Recording a Travel Expense always needs a receipt.'); // @pest-mutate-ignore (validation requires the receipt)

        $recordTravelExpense->record($travelRequest, $actor, $request->travelExpense(), $receipt);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense added.')]);

        return to_route('travel-requests.show', $travelRequest);
    }

    public function update(TravelExpenseRequest $request, TravelExpense $travelExpense, #[CurrentUser] Employee $actor, ChangeTravelExpense $changeTravelExpense): RedirectResponse
    {
        $changed = $changeTravelExpense->change($travelExpense, $actor, $request->travelExpense(), $request->receipt());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense changed.')]);

        return to_route('travel-requests.show', $travelExpense->travel_request_id);
    }

    public function destroy(TravelExpense $travelExpense, DeleteTravelExpense $deleteTravelExpense): RedirectResponse
    {
        $deleted = $deleteTravelExpense->delete($travelExpense);
        abort_if(! $deleted, 403); // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this delete)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense deleted.')]);

        return to_route('travel-requests.show', $travelExpense->travel_request_id);
    }
}
