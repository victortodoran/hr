<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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
        return self::inEach(collect([$employee]), $years)[$employee->id];
    }

    /**
     * The Paid Days left in each of the given years, in their order, for
     * each of the Employees, by Employee id: their Paid Days, which every
     * year gets in full, minus the weekdays that year of their Pending and
     * Approved Paid Time Off, read for all of them at once. It is negative
     * when their Paid Days were lowered below what is already taken.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  list<int>  $years
     * @return array<int, list<PaidDaysLeftInYear>>
     */
    public static function inEach(Collection $employees, array $years): array
    {
        $paidTimeOff = $years === [] ? collect() : TimeOff::query()
            ->ofEmployees(Employee::query()->whereKey($employees->pluck('id')->all()))
            ->standing()
            ->paid()
            ->overlapping(CarbonImmutable::createFromDate(min($years), 1, 1), CarbonImmutable::createFromDate(max($years), 12, 31)) // @pest-mutate-ignore (the surviving mutants only widen the years, and weekdaysIn() counts nothing outside them; the narrowing ones are tested)
            ->get(['employee_id', 'first_date', 'last_date'])
            ->groupBy('employee_id');

        return $employees->mapWithKeys(fn (Employee $employee): array => [
            $employee->id => array_map(fn (int $year): array => [
                'year' => $year,
                'days' => $employee->paid_days - $paidTimeOff->get($employee->id, collect())->sum(fn (TimeOff $timeOff): int => $timeOff->weekdaysIn($year)),
            ], $years),
        ])->all();
    }
}
