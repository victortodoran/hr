<?php

namespace App\Actions\Travel;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use Illuminate\Http\UploadedFile;

class ChangeTravelExpense
{
    use WritesTravelExpenses;

    /**
     * Only a Pending or Declined row changes, so an approval made since the
     * policy check wins, and the new receipt is then deleted again.
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
