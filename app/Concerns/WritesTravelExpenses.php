<?php

namespace App\Concerns;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

/**
 * What recording, changing and deleting a Travel Expense share: the state
 * a recorded or changed one takes, keeping its receipt on the private
 * disk, and deleting one no Travel Expense points to any more.
 */
trait WritesTravelExpenses
{
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
