<?php

namespace App\Actions;

use App\Concerns\WritesTravelExpenses;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Http\UploadedFile;

/**
 * Records a Travel Expense on one of the Employee's own Travel Requests,
 * with its receipt.
 */
class RecordTravelExpense
{
    use WritesTravelExpenses;

    /**
     * Record the Travel Expense, keeping its receipt on the private disk. It
     * is Pending until the Employee's Manager decides, except for an
     * Employee with no Manager, whose Travel Expense is Approved at once
     * with nobody approving. When the write fails, the receipt is deleted
     * again.
     *
     * @param  array{amount: int, paid_on: string, category: string, description: string}  $details
     */
    public function record(TravelRequest $travelRequest, Employee $actor, array $details, UploadedFile $receipt): void
    {
        $receiptPath = $this->keep($receipt);
        $travelExpense = $travelRequest->travelExpenses()->make([
            ...$details,
            'receipt_path' => $receiptPath,
        ]);
        $travelExpense->forceFill($this->newState($actor));

        $this->discardingOnFailure($receiptPath, fn (): bool => $travelExpense->save());
    }
}
