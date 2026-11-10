<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Where an Employee is in their period of work on a given day. It is worked
 * out from their Start Date and Leaving Date, never stored.
 */
enum EmploymentStatus: string
{
    case Future = 'future';
    case Current = 'current';
    case Former = 'former';

    /**
     * The status on a day: future before the Start Date, former from the day
     * after the Leaving Date, and current in between.
     */
    public static function on(CarbonInterface $day, CarbonInterface $startDate, ?CarbonInterface $leavingDate): self
    {
        return match (true) { // @pest-mutate-ignore (coverage never attributes this line to a test, like class attributes)
            $day->lt($startDate->copy()->startOfDay()) => self::Future,
            $leavingDate !== null && $day->gt($leavingDate->copy()->endOfDay()) => self::Former,
            default => self::Current,
        };
    }
}
