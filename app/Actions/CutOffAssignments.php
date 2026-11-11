<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Project;
use Carbon\CarbonInterface;

/**
 * Ends an Employee's or a Project's Assignments by a last day, so nobody
 * appears to work after leaving or on a finished Project. Assignments that
 * would only have started after that day never happen, so they are removed.
 */
class CutOffAssignments
{
    public function at(Employee|Project $owner, CarbonInterface $lastDay): void
    {
        $owner->assignments()->whereDate('start_date', '>', $lastDay)->delete();

        $owner->assignments()
            ->where(fn ($assignments) => $assignments->whereNull('end_date')->orWhereDate('end_date', '>', $lastDay))
            ->update(['end_date' => $lastDay->toDateString()]);
    }
}
