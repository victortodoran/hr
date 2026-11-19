<?php

namespace App\Support;

use App\Models\Employee;

/**
 * An Employee's Paid Days left as Home, the Time Off page and their
 * Employee page show them: this year's and, from 1 October, next year's
 * beside it, since Paid Time Off can be requested into next year.
 */
final class PaidDaysLeft
{
    /**
     * The month from which next year's Paid Days left show too.
     */
    private const int NEXT_YEAR_FROM_MONTH = 10;

    /**
     * The Paid Days left in each year shown, this year first, counting
     * Pending and Approved Paid Time Off.
     *
     * @return list<array{year: int, days: int}>
     */
    public static function of(Employee $employee): array
    {
        $years = today()->month >= self::NEXT_YEAR_FROM_MONTH
            ? [today()->year, today()->year + 1]
            : [today()->year];

        return array_map(fn (int $year): array => [
            'year' => $year,
            'days' => $employee->paidDaysLeftIn($year),
        ], $years);
    }
}
