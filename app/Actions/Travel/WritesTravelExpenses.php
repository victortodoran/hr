<?php

namespace App\Actions\Travel;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

trait WritesTravelExpenses
{
    /**
     * An Employee with no Manager has nobody to approve, so theirs is Approved
     * at once.
     *
     * @return array{state: TravelExpenseState, approved_at: CarbonInterface|null}
     */
    private function newState(Employee $actor): array
    {
        return $actor->hasNoManager()
            ? ['state' => TravelExpenseState::Approved, 'approved_at' => now()]
            : ['state' => TravelExpenseState::Pending, 'approved_at' => null];
    }

    private function keep(UploadedFile $receipt): string
    {
        $path = $receipt->store(TravelExpense::RECEIPT_FOLDER, TravelExpense::RECEIPT_DISK);

        return $path === false ? throw new LogicException('The receipt could not be stored.') : $path; // @pest-mutate-ignore (only false when the disk refuses the write)
    }

    /**
     * The receipt is deleted again when the write throws, so no file is left
     * that no Travel Expense points to.
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

    private function discard(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(TravelExpense::RECEIPT_DISK)->delete($path);
        }
    }
}
