<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelExpenseState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelExpenseRequest;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use LogicException;
use Throwable;

/**
 * Lets an Employee record, change and delete the Travel Expenses of their
 * own Travel Requests, each with its receipt.
 */
class TravelExpenseController extends Controller
{
    /**
     * Record a Travel Expense on the Travel Request, keeping its receipt on
     * the private disk. It is Pending until the Employee's Manager decides,
     * except for an Employee with no Manager, whose Travel Expense is
     * Approved at once with nobody approving.
     */
    public function store(TravelExpenseRequest $request, TravelRequest $travelRequest, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $receipt = $request->receipt() ?? throw new LogicException('Recording a Travel Expense always needs a receipt.'); // @pest-mutate-ignore (validation requires the receipt)

        $receiptPath = $this->keep($receipt);
        $travelExpense = $travelRequest->travelExpenses()->make([
            ...$request->travelExpense(),
            'receipt_path' => $receiptPath,
        ]);
        $travelExpense->forceFill($this->newState($actor));

        $this->discardingOnFailure($receiptPath, fn (): bool => $travelExpense->save());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense added.')]);

        return to_route('travel-requests.index');
    }

    /**
     * Correct a Travel Expense, replacing its receipt when a new one is
     * sent and deleting the old file. A Declined one goes back to Pending,
     * without who declined it or why, or is Approved at once for an
     * Employee with no Manager, as when recording. The write only changes
     * one still
     * Pending or Declined, so an approval made since the policy check is
     * never undone.
     */
    public function update(TravelExpenseRequest $request, TravelExpense $travelExpense, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $receipt = $request->receipt();
        $newPath = $receipt === null ? null : $this->keep($receipt);

        $changed = $this->discardingOnFailure($newPath, fn (): int => TravelExpense::query()
            ->whereKey($travelExpense->id)
            ->whereIn('state', TravelExpenseState::open())
            ->update([
                ...$request->travelExpense(),
                'receipt_path' => $newPath ?? $travelExpense->receipt_path,
                ...$this->newState($actor),
                'declined_by' => null,
                'declined_at' => null,
                'decline_reason' => null,
                'updated_at' => now(),
            ]));

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this write)
            $this->discard($newPath);
            abort(403);
        }

        if ($newPath !== null) {
            $this->discard($travelExpense->receipt_path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense changed.')]);

        return to_route('travel-requests.index');
    }

    /**
     * Delete a Travel Expense recorded by mistake, with its receipt. The
     * delete only removes one still Pending or Declined, so an approval
     * made since the policy check is never undone.
     */
    public function destroy(TravelExpense $travelExpense): RedirectResponse
    {
        $deleted = TravelExpense::query()
            ->whereKey($travelExpense->id)
            ->whereIn('state', TravelExpenseState::open())
            ->delete();
        abort_if($deleted === 0, 403); // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this delete)
        $this->discard($travelExpense->receipt_path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Expense deleted.')]);

        return to_route('travel-requests.index');
    }

    /**
     * The state a recorded or changed Travel Expense takes: Pending for its
     * Manager to decide, or Approved at once with nobody approving for an
     * Employee with no Manager.
     *
     * @return array{state: TravelExpenseState, approved_at: CarbonInterface|null}
     */
    private function newState(Employee $actor): array
    {
        return $actor->hasNoManager()
            ? ['state' => TravelExpenseState::Approved, 'approved_at' => now()]
            : ['state' => TravelExpenseState::Pending, 'approved_at' => null];
    }

    /**
     * Keep a receipt on the private disk under a generated name, returning
     * its path there.
     */
    private function keep(UploadedFile $receipt): string
    {
        $path = $receipt->store(TravelExpense::RECEIPT_FOLDER, TravelExpense::RECEIPT_DISK);

        return $path === false ? throw new LogicException('The receipt could not be stored.') : $path; // @pest-mutate-ignore (only false when the disk refuses the write)
    }

    /**
     * Run a database write for a receipt just kept, deleting that receipt
     * again when the write throws, so no file is left that no Travel Expense
     * points to.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $write
     * @return TResult
     */
    private function discardingOnFailure(?string $receiptPath, Closure $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $failure) { // @pest-mutate-ignore (only reached when the database refuses the write)
            $this->discard($receiptPath);

            throw $failure;
        }
    }

    /**
     * Delete a receipt file no Travel Expense points to any more.
     */
    private function discard(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(TravelExpense::RECEIPT_DISK)->delete($path);
        }
    }
}
