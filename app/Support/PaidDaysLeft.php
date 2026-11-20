<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** @phpstan-type PaidDaysLeftInYear array{year: int, days: int} */
final class PaidDaysLeft
{
    /** Paid Time Off can be requested into next year, so from this month next year's Paid Days left show too. */
    private const int NEXT_YEAR_FROM_MONTH = 10;

    /** @return list<PaidDaysLeftInYear> */
    public static function of(Employee $employee): array
    {
        $today = today();

        return self::in($employee, $today->month >= self::NEXT_YEAR_FROM_MONTH
            ? [$today->year, $today->year + 1]
            : [$today->year]);
    }

    /**
     * @param  list<int>  $years
     * @return list<PaidDaysLeftInYear>
     */
    public static function in(Employee $employee, array $years): array
    {
        return self::inEach(collect([$employee]), $years)[$employee->id];
    }

    /**
     * Negative when an Employee's Paid Days were lowered below what is already taken.
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
