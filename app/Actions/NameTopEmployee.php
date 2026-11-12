<?php

namespace App\Actions;

use App\Enums\WorkEntryState;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hands the top spot to another Employee. The old top Employee then reports
 * to the new one, so there is always exactly one top Employee.
 */
class NameTopEmployee
{
    /**
     * The top Employee has no Manager, so nobody could approve their Work
     * Entries: someone with Pending ones can't take the top spot.
     *
     * @throws ValidationException
     */
    public function handOverTo(Employee $successor): void
    {
        if ($successor->workEntries()->where('state', WorkEntryState::Pending)->exists()) {
            throw ValidationException::withMessages([
                'employee' => __(':name has Pending Work Entries, which nobody could approve once :name is the top Employee. Have their Manager approve them first, or delete them.', ['name' => $successor->name]),
            ]);
        }

        DB::transaction(function () use ($successor): void {
            $previous = Employee::query()->where('is_top', true)->lockForUpdate()->sole();

            $previous->is_top = false;
            $previous->manager_id = $successor->id;
            $previous->save();

            $successor->is_top = true;
            $successor->manager_id = null;
            $successor->save();
        });
    }
}
