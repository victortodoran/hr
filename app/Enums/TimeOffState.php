<?php

namespace App\Enums;

enum TimeOffState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public static function standing(): array
    {
        return [self::Pending, self::Approved];
    }
}
