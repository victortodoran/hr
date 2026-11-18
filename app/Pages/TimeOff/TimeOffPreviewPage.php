<?php

namespace App\Pages\TimeOff;

use App\Models\Employee;
use App\Models\TimeOff;

/**
 * The live count in the Time Off request dialog as the JSON it answers
 * with.
 */
final class TimeOffPreviewPage
{
    /**
     * What requesting the unsaved Time Off would take: its weekdays and,
     * when Paid, for each year it touches the Paid Days it would use and
     * those left after it.
     *
     * @return array{weekdays: int, years: array<int, array{year: int, uses: int, left_after: int}>}
     */
    public static function props(TimeOff $timeOff, Employee $employee): array
    {
        $years = $timeOff->is_paid ? $employee->paidDaysUsedBy($timeOff) : [];

        return [
            'weekdays' => $timeOff->weekdays(),
            'years' => array_map(fn (array $year): array => [
                'year' => $year['year'],
                'uses' => $year['uses'],
                'left_after' => $year['left'] - $year['uses'],
            ], $years),
        ];
    }
}
