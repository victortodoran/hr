<?php

namespace App\Actions\TimeOff;

use App\Enums\TimeOffState;
use App\Models\Employee;

class RequestTimeOff
{
    /**
     * An Employee with no Manager has nobody to decide, so their Time Off is
     * Approved at once.
     *
     * @param  array{first_date: string, last_date: string, is_paid: bool}  $attributes
     */
    public function request(Employee $employee, array $attributes): void
    {
        $timeOff = $employee->timeOff()->make($attributes);

        if ($employee->hasNoManager()) {
            $timeOff->forceFill(['state' => TimeOffState::Approved, 'decided_at' => now()]);
        }

        $timeOff->save();
    }
}
