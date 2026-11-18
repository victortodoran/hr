<?php

namespace App\Actions;

use App\Enums\TimeOffState;
use App\Models\Employee;

/**
 * Requests Time Off for an Employee.
 */
class RequestTimeOff
{
    /**
     * Request the Time Off. It is Pending until the Employee's Manager
     * decides, except for an Employee with no Manager, whose Time Off is
     * Approved at once with nobody deciding.
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
