<?php

namespace App\Actions\Travel;

use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Http\UploadedFile;

class RecordTravelExpense
{
    use WritesTravelExpenses;

    /** @param  array{amount: int, paid_on: string, category: string, description: string}  $details */
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
