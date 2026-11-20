<?php

namespace App\Http\Controllers\Travel;

use App\Actions\ChangeTravelExpense;
use App\Actions\DeleteTravelExpense;
use App\Actions\RecordTravelExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelExpenseRequest;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Pages\Travel\TravelExpenseFormPage;
use App\Pages\Travel\TravelRequestPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * Lets an Employee record, change and delete the Travel Expenses of their
 * own Travel Requests, each with its receipt, in a sheet over the Travel
 * Request's page.
 */
class TravelExpenseController extends Controller
{
    /**
     * Show the Travel Request's page with the sheet for adding a Travel
     * Expense open over it (rule L14), so the form has a URL of its own
     * (TravelExpenseFormPage).
     */
    public function create(TravelRequest $travelRequest, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', [
            ...TravelRequestPage::props($travelRequest, $actor),
            ...TravelExpenseFormPage::create(),
        ]);
    }

    /**
     * Show the Travel Request's page with the sheet for editing the Travel
     * Expense open over it, filled in as it would be typed
     * (TravelExpenseFormPage).
     */
    public function edit(TravelRequest $travelRequest, TravelExpense $travelExpense, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', [
            ...TravelRequestPage::props($travelRequest, $actor),
            ...TravelExpenseFormPage::edit($travelExpense, $actor),
        ]);
    }

    /**
     * Record a Travel Expense on the Travel Request, with its receipt. It is
     * Pending until the Employee's Manager decides, except for an Employee
     * with no Manager, whose Travel Expense is Approved at once.
     */
    public function store(TravelExpenseRequest $request, TravelRequest $travelRequest, #[CurrentUser] Employee $actor, RecordTravelExpense $recordTravelExpense): RedirectResponse
    {
        $receipt = $request->receipt() ?? throw new LogicException('Recording a Travel Expense always needs a receipt.'); // @pest-mutate-ignore (validation requires the receipt)

        $recordTravelExpense->record($travelRequest, $actor, $request->travelExpense(), $receipt);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense added.')]);

        return to_route('travel-requests.show', $travelRequest);
    }

    /**
     * Correct a Travel Expense, replacing its receipt when a new one is
     * sent. One approved since the policy check is refused, never changed.
     */
    public function update(TravelExpenseRequest $request, TravelExpense $travelExpense, #[CurrentUser] Employee $actor, ChangeTravelExpense $changeTravelExpense): RedirectResponse
    {
        $changed = $changeTravelExpense->change($travelExpense, $actor, $request->travelExpense(), $request->receipt());
        abort_if(! $changed, 403); // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this write)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense changed.')]);

        return to_route('travel-requests.show', $travelExpense->travel_request_id);
    }

    /**
     * Delete a Travel Expense recorded by mistake, with its receipt. One
     * approved since the policy check is refused, never deleted.
     */
    public function destroy(TravelExpense $travelExpense, DeleteTravelExpense $deleteTravelExpense): RedirectResponse
    {
        $deleted = $deleteTravelExpense->delete($travelExpense);
        abort_if(! $deleted, 403); // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this delete)

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense deleted.')]);

        return to_route('travel-requests.show', $travelExpense->travel_request_id);
    }
}
