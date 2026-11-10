<?php

namespace App\Actions;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Hands the top spot to another Employee. The old top Employee then reports
 * to the new one, so there is always exactly one top Employee.
 */
class NameTopEmployee
{
    public function handOverTo(Employee $successor): void
    {
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
