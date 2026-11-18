<?php

namespace App\Actions;

use App\Concerns\WritesTravelExpenses;
use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use Illuminate\Http\UploadedFile;

/**
 * Corrects a Travel Expense the Employee recorded, keeping its receipt or
 * replacing it.
 */
class ChangeTravelExpense
{
    use WritesTravelExpenses;

    /**
     * Correct the Travel Expense and return whether it changed, replacing
     * its receipt when a new one is sent and deleting the old file. A
     * Declined one goes back to Pending, without who declined it or why, or
     * is Approved at once for an Employee with no Manager, as when
     * recording. The write only changes one still Pending or Declined, so
     * an approval made since the policy check is never undone; then nothing
     * changes, the new receipt is deleted again and false is returned.
     *
     * @param  array{amount: int, paid_on: string, category: string, description: string}  $changes
     */
    public function change(TravelExpense $travelExpense, Employee $actor, array $changes, ?UploadedFile $receipt): bool
    {
        $newPath = $receipt === null ? null : $this->keep($receipt);

        $changed = $this->discardingOnFailure($newPath, fn (): int => TravelExpense::query()
            ->whereKey($travelExpense->id)
            ->whereIn('state', TravelExpenseState::open())
            ->update([
                ...$changes,
                'receipt_path' => $newPath ?? $travelExpense->receipt_path,
                ...$this->newState($actor),
                'declined_by' => null,
                'declined_at' => null,
                'decline_reason' => null,
                'updated_at' => now(),
            ]));

        if ($changed === 0) { // @pest-mutate-ignore (only reached when the Travel Expense is approved between the policy check and this write)
            $this->discard($newPath);

            return false;
        }

        if ($newPath !== null) {
            $this->discard($travelExpense->receipt_path);
        }

        return true;
    }
}
