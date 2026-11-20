<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum EmploymentStatus: string
{
    case Future = 'future';
    case Current = 'current';
    case Former = 'former';

    public static function on(CarbonInterface $day, CarbonInterface $startDate, ?CarbonInterface $leavingDate): self
    {
        return match (true) { // @pest-mutate-ignore (coverage never attributes this line to a test, like class attributes)
            $day->lt($startDate->copy()->startOfDay()) => self::Future,
            $leavingDate !== null && $day->gt($leavingDate->copy()->endOfDay()) => self::Former,
            default => self::Current,
        };
    }
}
