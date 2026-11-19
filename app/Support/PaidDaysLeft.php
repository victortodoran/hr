<?php

namespace App\Support;

use App\Models\Employee;

/**
 * An Employee's Paid Days left, year by year, counting Pending and Approved
 * Paid Time Off. Home, the Time Off page and their Employee page show this
 * year's and, from 1 October, next year's beside it, since Paid Time Off can
 * be requested into next year.
 *
 * @phpstan-type PaidDaysLeftInYear array{year: int, days: int}
 */
final class PaidDaysLeft
{
    /**
     * The month from which next year's Paid Days left show too.
     */
    private const int NEXT_YEAR_FROM_MONTH = 10;

    /**
     * The Paid Days left this year and, from October, next year.
     *
     * @return list<PaidDaysLeftInYear>
     */
    public static function of(Employee $employee): array
    {
        $today = today();

        return self::in($employee, $today->month >= self::NEXT_YEAR_FROM_MONTH
            ? [$today->year, $today->year + 1]
            : [$today->year]);
    }

    /**
     * The Paid Days left in each of the given years, in their order.
     *
     * @param  list<int>  $years
     * @return list<PaidDaysLeftInYear>
     */
    public static function in(Employee $employee, array $years): array
    {
        return array_map(fn (int $year): array => [
            'year' => $year,
            'days' => $employee->paidDaysLeftIn($year),
        ], $years);
    }
}
